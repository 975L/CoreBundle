<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Security\RolePreview;
use c975L\ConfigBundle\Security\Voter\RolePreviewRoleVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\InMemoryUser;

class RolePreviewTest extends TestCase
{
    private const array OWNER_ROLES = ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER'];

    private Session $session;

    private RequestStack $requestStack;

    // A request carrying a session cookie, as any signed-in visitor's does
    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $request->cookies->set($this->session->getName(), 'id');

        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
    }

    // The owner holds every role, so every level below the top is offered, members last
    public function testTheOwnerCanPreviewEveryLowerLevel(): void
    {
        $this->assertSame(['admin', 'editor', 'contributor', 'member'], array_keys($this->rolePreview()->availableLevels(self::OWNER_ROLES)));
    }

    public function testAnEditorCanPreviewOnlyTheLevelsBelow(): void
    {
        $this->assertSame(['contributor', 'member'], array_keys($this->rolePreview()->availableLevels(['ROLE_EDITOR', 'ROLE_USER'])));
    }

    public function testAContributorCanPreviewTheMember(): void
    {
        $this->assertSame(['member'], array_keys($this->rolePreview()->availableLevels(['ROLE_CONTRIBUTOR', 'ROLE_USER'])));
    }

    // A member stands at the bottom, with nothing to look through
    public function testAMemberGetsNoLevel(): void
    {
        $this->assertSame([], $this->rolePreview()->availableLevels(['ROLE_USER']));
    }

    public function testWithoutPreviewTheTokenKeepsItsRoles(): void
    {
        $this->assertSame(self::OWNER_ROLES, $this->rolePreview()->effectiveRoles($this->token(self::OWNER_ROLES)));
    }

    public function testAPreviewReducesTheRolesToTheLevel(): void
    {
        $this->session->set(RolePreview::SESSION_KEY, 'editor');

        $this->assertSame(['ROLE_EDITOR', 'ROLE_USER'], $this->rolePreview()->effectiveRoles($this->token(self::OWNER_ROLES)));
    }

    // The escalation guard: a level at or above the account's own is never honoured, however it reached the session
    public function testALevelAboveTheAccountIsIgnored(): void
    {
        $this->session->set(RolePreview::SESSION_KEY, 'admin');
        $roles = ['ROLE_EDITOR', 'ROLE_USER'];

        $this->assertSame($roles, $this->rolePreview()->effectiveRoles($this->token($roles)));
    }

    // Asked on every isGranted() of every request: a visitor without a session must not be given one
    public function testNoSessionIsStartedForAVisitorWithoutOne(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        $this->assertNull($this->rolePreview()->activeLevel(self::OWNER_ROLES));
        $this->assertFalse($session->isStarted());
    }

    // The voter taking Symfony's place answers for the previewed level
    public function testTheRoleVoterRefusesARoleThePreviewDropped(): void
    {
        $this->session->set(RolePreview::SESSION_KEY, 'editor');
        $voter = new RolePreviewRoleVoter($this->rolePreview(), new RoleHierarchy([]));
        $token = $this->token(self::OWNER_ROLES);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['ROLE_ADMIN']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['ROLE_EDITOR']));
    }

    // On a site declaring a role_hierarchy the voter stands in for the hierarchy one, so the previewed level still reaches the roles it implies there
    public function testTheRoleVoterExpandsThePreviewedLevelThroughTheRoleHierarchy(): void
    {
        $this->session->set(RolePreview::SESSION_KEY, 'editor');
        $voter = new RolePreviewRoleVoter($this->rolePreview(), new RoleHierarchy(['ROLE_EDITOR' => ['ROLE_WRITER']]));
        $token = $this->token(self::OWNER_ROLES);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['ROLE_WRITER']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['ROLE_ADMIN']));
    }

    private function rolePreview(): RolePreview
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => match ($slug) {
            'site-role-admin' => 'ROLE_ADMIN',
            'site-role-editor' => 'ROLE_EDITOR',
            'site-role-contributor' => 'ROLE_CONTRIBUTOR',
            default => null,
        });

        return new RolePreview($configService, $this->requestStack);
    }

    private function token(array $roles): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new InMemoryUser('owner', null, $roles), 'main', $roles);
    }
}
