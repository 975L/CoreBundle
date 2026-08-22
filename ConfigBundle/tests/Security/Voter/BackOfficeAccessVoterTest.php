<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security\Voter;

use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class BackOfficeAccessVoterTest extends TestCase
{
    // Each of the three bars opens the back office on its own: no role_hierarchy is shipped, so an account holding one of them holds nothing else
    public function testEachBarOpensTheBackOfficeOnItsOwn(): void
    {
        foreach (['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'] as $role) {
            $this->assertSame(
                VoterInterface::ACCESS_GRANTED,
                $this->vote([$role]),
                sprintf('An account holding only %s is locked out of the back office', $role),
            );
        }
    }

    // The very account the dashboard used to let in and the editor bar alone would now turn away
    public function testAnAccountHoldingOnlyTheAdminBarIsNotLockedOut(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(['ROLE_ADMIN']));
    }

    public function testAUserHoldingNoneOfThemIsRefused(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(['ROLE_USER']));
    }

    // A site that emptied one of the two role configs would otherwise have isGranted('') asked of it, which no voter answers usefully
    public function testAnUnsetRoleConfigIsSkippedRatherThanAsked(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('');

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            new BackOfficeAccessVoter($configService, $security)->vote($this->createStub(TokenInterface::class), null, [BackOfficeAccessVoter::ACCESS]),
        );
    }

    public function testItAnswersNoOtherAttribute(): void
    {
        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->vote(['ROLE_ADMIN'], 'SOME_OTHER_ATTRIBUTE'),
        );
    }

    private function vote(array $heldRoles, string $attribute = BackOfficeAccessVoter::ACCESS): int
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $slug) => 'site-role-editor' === $slug ? 'ROLE_EDITOR' : 'ROLE_ADMIN'
        );

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $role) => \in_array($role, $heldRoles, true)
        );

        return new BackOfficeAccessVoter($configService, $security)
            ->vote($this->createStub(TokenInterface::class), null, [$attribute]);
    }
}
