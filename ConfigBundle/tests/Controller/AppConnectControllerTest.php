<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Controller\AppConnectController;
use c975L\ConfigBundle\Service\AppTokenManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

// The page a member agrees on hands a code to the site's own app only, and the calls the app makes say nothing of why a code was refused
class AppConnectControllerTest extends TestCase
{
    private const string CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    private function createController(bool $signedIn = true, bool $allowed = true, ?string $token = 'the-token', bool $csrf = true): AppConnectController
    {
        $manager = $this->createStub(AppTokenManager::class);
        $manager->method('schemes')->willReturn($allowed ? ['papacalin'] : []);
        $manager->method('isAllowedRedirect')->willReturn($allowed);
        $manager->method('createCode')->willReturn('the-code');
        $manager->method('exchange')->willReturn($token);

        $controller = new AppConnectController($manager);

        $tokenStorage = new TokenStorage();
        if ($signedIn) {
            $user = $this->createStub(UserInterface::class);
            $user->method('getUserIdentifier')->willReturn('parent@example.com');
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));
        }

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<page>');

        $csrfManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn($csrf);

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $container->set('security.csrf.token_manager', $csrfManager);
        $controller->setContainer($container);

        return $controller;
    }

    private function connect(string $method = 'GET', string $redirect = 'papacalin://connect', string $challenge = self::CHALLENGE): Request
    {
        return Request::create('/app/connect?' . http_build_query(['redirect' => $redirect, 'challenge' => $challenge, 'state' => 'xyz']), $method, 'POST' === $method ? ['_token' => 'csrf'] : []);
    }

    // An address that is not one of the site's apps gets no page to borrow the site's name with
    public function testAnAddressThatIsNoAppOfTheSiteIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(allowed: false)->connect($this->connect());
    }

    // No challenge, no code: the app must have something to prove later
    public function testAMalformedChallengeIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->connect($this->connect(challenge: 'too-short'));
    }

    // A visitor is sent to sign in first, which is the firewall's entry point's job
    public function testAVisitorIsSentToSignIn(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(signedIn: false)->connect($this->connect());
    }

    // Asked first: nothing is issued on a simple visit of the address
    public function testTheMemberIsAskedBeforeAnythingIsIssued(): void
    {
        $response = $this->createController()->connect($this->connect());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<page>', $response->getContent());
    }

    // Agreed: the app is handed its code, and the state it sent to recognise its own request
    public function testAgreeingSendsTheCodeBackToTheApp(): void
    {
        $response = $this->createController()->connect($this->connect('POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('papacalin://connect?code=the-code&state=xyz', $response->headers->get('Location'));
    }

    // The agreement must come from the page itself
    public function testAgreeingFromElsewhereIsRefused(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(csrf: false)->connect($this->connect('POST'));
    }

    // The trade, kept by nothing on the way
    public function testACodeIsTradedForAToken(): void
    {
        $request = Request::create('/app/api/token', 'POST', content: json_encode(['code' => 'the-code', 'verifier' => 'the-verifier']));
        $response = $this->createController()->token($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['token' => 'the-token'], json_decode((string) $response->getContent(), true));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    // One answer for every way a code can be wrong
    public function testARefusedCodeIsABadRequest(): void
    {
        $controller = $this->createController(token: null);

        $this->assertSame(400, $controller->token(Request::create('/app/api/token', 'POST', content: json_encode(['code' => 'x', 'verifier' => 'y'])))->getStatusCode());
        $this->assertSame(400, $controller->token(Request::create('/app/api/token', 'POST', content: 'not json'))->getStatusCode());
    }

    // The account behind the token, for the app to say whose it is
    public function testTheAppIsToldWhoseAccountItIsConnectedTo(): void
    {
        $response = $this->createController()->me();

        $this->assertSame(['identifier' => 'parent@example.com'], json_decode((string) $response->getContent(), true));
        $this->assertSame(401, $this->createController(signedIn: false)->me()->getStatusCode());
    }

    // A site with no app answers nothing under these addresses
    public function testASiteWithNoAppOffersNoCall(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(allowed: false)->me();
    }
}
