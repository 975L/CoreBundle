<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\RolePreviewController;
use c975L\ConfigBundle\Security\RolePreview;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class RolePreviewControllerTest extends TestCase
{
    private const array OWNER_ROLES = ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER'];

    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
    }

    // The page the link was clicked on, as its referer says
    private function request(string $referer): Request
    {
        $request = Request::create('http://localhost/role-preview/editor');
        $request->headers->set('referer', $referer);
        $request->setSession($this->session);

        return $request;
    }

    // A signed-in account holding these roles, and a token the CSRF check accepts or not - the three services AbstractController reads from its container
    private function controller(Request $request, array $roles = self::OWNER_ROLES, bool $validToken = true): RolePreviewController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => match ($slug) {
            'site-role-admin' => 'ROLE_ADMIN',
            'site-role-editor' => 'ROLE_EDITOR',
            'site-role-contributor' => 'ROLE_CONTRIBUTOR',
            default => null,
        });

        $requestStack = new RequestStack([$request]);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('owner', null, $roles), 'main', $roles));

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($validToken);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/management');

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.csrf.token_manager', $csrfTokenManager);
        $container->set('router', $router);

        $controller = new RolePreviewController(new RolePreview($configService, $requestStack));
        $controller->setContainer($container);

        return $controller;
    }

    // A back-office screen may be refused to the level now previewed, so the preview opens on the dashboard
    public function testAPreviewStartedFromTheBackOfficeOpensOnTheDashboard(): void
    {
        $request = $this->request('http://localhost/management/page');

        $response = $this->controller($request)->start($request, 'editor');

        $this->assertSame('editor', $this->session->get(RolePreview::SESSION_KEY));
        $this->assertSame('/management', $response->getTargetUrl());
    }

    // A member is refused the whole back office, so that preview lands on the home page
    public function testPreviewingAsAMemberLeavesTheBackOffice(): void
    {
        $request = $this->request('http://localhost/management');

        $this->assertSame('/', $this->controller($request)->start($request, RolePreview::MEMBER)->getTargetUrl());
    }

    public function testAPageOfThePublicSiteIsReturnedTo(): void
    {
        $request = $this->request('http://localhost/animaux');

        $this->assertSame('http://localhost/animaux', $this->controller($request)->start($request, 'editor')->getTargetUrl());
    }

    // An open redirect otherwise: a link whose referer is another site's page
    public function testAnotherSitesPageIsNeverReturnedTo(): void
    {
        $request = $this->request('https://evil.test/animaux');

        $this->assertSame('/management', $this->controller($request)->stop($request)->getTargetUrl());
    }

    // The escalation guard on the way in: a level at or above the account's own is never stored
    public function testALevelAtOrAboveTheAccountIsRefused(): void
    {
        $request = $this->request('http://localhost/management');

        $this->expectException(AccessDeniedException::class);

        $this->controller($request, ['ROLE_EDITOR', 'ROLE_USER'])->start($request, 'admin');
    }

    // The links are plain GET ones, so the token is what keeps another site from switching an account's level
    public function testAnInvalidTokenIsRefused(): void
    {
        $request = $this->request('http://localhost/management');

        $this->expectException(AccessDeniedException::class);

        $this->controller($request, validToken: false)->start($request, 'editor');
    }

    public function testStopClearsThePreview(): void
    {
        $this->session->set(RolePreview::SESSION_KEY, 'editor');
        $request = $this->request('http://localhost/animaux');

        $this->controller($request)->stop($request);

        $this->assertFalse($this->session->has(RolePreview::SESSION_KEY));
    }
}
