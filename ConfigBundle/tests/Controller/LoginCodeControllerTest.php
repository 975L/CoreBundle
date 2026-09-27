<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\LoginCodeController;
use c975L\ConfigBundle\Security\LoginCode;
use c975L\ConfigBundle\Security\TrustedDevice;
use c975L\ConfigBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// The code page run through a real form, LoginCode stubbed to answer each outcome
class LoginCodeControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private Session $session;

    // The right code goes on to where the login was heading, the browser remembered
    public function testTheRightCodeGoesOnAndRemembersTheBrowser(): void
    {
        $response = $this->controller(LoginCode::VALID)->index($this->submit());

        $this->assertSame('/management', $response->headers->get('Location'));
        $this->assertSame(TrustedDevice::COOKIE, $response->headers->getCookies()[0]->getName());
    }

    // A wrong code shows the page again
    public function testAWrongCodeShowsThePageAgain(): void
    {
        $response = $this->controller(LoginCode::INVALID)->index($this->submit());

        $this->assertSame('page', $response->getContent());
    }

    // Too many wrong codes log out
    public function testTooManyWrongCodesLogOut(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('logout')->willReturn(new RedirectResponse('/logged-out'));

        $response = $this->controller(LoginCode::EXHAUSTED, security: $security)->index($this->submit());

        $this->assertSame('/logged-out', $response->headers->get('Location'));
        $this->assertSame(['flash.login_code_exhausted'], $this->session->getFlashBag()->get('danger'));
    }

    // Nothing awaited, nothing to type: the home page
    public function testNothingAwaitedSendsHome(): void
    {
        $response = $this->controller(LoginCode::VALID, pending: false)->index($this->submit());

        $this->assertSame('/', $response->headers->get('Location'));
    }

    // Another code asked, the page says whether it left
    public function testResendSaysWhetherTheCodeLeft(): void
    {
        $request = Request::create('/login/code/resend', 'POST', ['_token' => 'token']);
        $controller = $this->controller(LoginCode::VALID, resent: true);
        $request->setSession($this->session);

        $controller->resend($request);

        $this->assertSame(['flash.login_code_resent'], $this->session->getFlashBag()->get('success'));
    }

    // The code as the page posts it, within the session the controller reads
    private function submit(): Request
    {
        $request = Request::create('/login/code', 'POST', ['login_code' => ['code' => '123456']]);
        $request->setSession($this->session);

        return $request;
    }

    // Over a LoginCode answering that outcome
    private function controller(string $result, bool $pending = true, bool $resent = false, ?Security $security = null): LoginCodeController
    {
        $loginCode = $this->createStub(LoginCode::class);
        $loginCode->method('isPending')->willReturn($pending);
        $loginCode->method('verify')->willReturn($result);
        $loginCode->method('finish')->willReturn('/management');
        $loginCode->method('resend')->willReturn($resent);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('page');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new UserStub(), 'main'));

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login/code');

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        $controller = new LoginCodeController($loginCode, new TrustedDevice('secret'), $security ?? $this->createStub(Security::class), $translator);
        $controller->setContainer($this->createContainer([
            'form.factory' => $formFactory,
            'request_stack' => new RequestStack([$request]),
            'security.token_storage' => $tokenStorage,
            'security.csrf.token_manager' => $csrfTokenManager,
            'router' => $urlGenerator,
            'twig' => $twig,
        ]));

        return $controller;
    }
}
