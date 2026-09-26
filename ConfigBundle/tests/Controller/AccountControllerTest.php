<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Account\AccountSectionBuilder;
use c975L\ConfigBundle\Account\AccountSectionProviderInterface;
use c975L\ConfigBundle\Controller\AccountController;
use c975L\ConfigBundle\Controller\OAuthLoginController;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\PasswordResetter;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ConfigBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// The action run through a real form and validator, the current password checked by Symfony's own UserPasswordValidator against a plaintext hasher. The firewall itself, sending an anonymous visitor to the login page, is covered by the scaffold's functional AccountControllerTest
class AccountControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private const string CURRENT_PASSWORD = 'Old-Passw0rd!';
    private const string NEW_PASSWORD = 'N3w-Str0ng!Pass';

    private Session $session;

    // What the page was rendered with, read back by the tests
    private array $rendered = [];

    // Any member reaches the page, a visitor being sent to the login form by the firewall
    public function testRequiresAMember(): void
    {
        $attributes = new \ReflectionClass(AccountController::class)->getAttributes(IsGranted::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('ROLE_USER', $attributes[0]->newInstance()->attribute);
    }

    // The profile's password form and the providers' sections, in their order
    public function testShowsThePasswordFormAndTheSections(): void
    {
        $provider = $this->createStub(AccountSectionProviderInterface::class);
        $provider->method('getAccountSections')->willReturn([
            ['title' => 'label.orders', 'translation_domain' => 'payment', 'template' => 'orders.html.twig', 'position' => 20],
            ['title' => 'label.credits', 'translation_domain' => 'purchasecredits', 'template' => 'credits.html.twig', 'position' => 10],
        ]);

        $response = $this->controller(providers: [$provider])->index($this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($this->rendered['passwordForm']);
        $this->assertSame(['label.credits', 'label.orders'], array_column($this->rendered['sections'], 'title'));
        // UserStub cannot be anonymized: AccountDeleteController would answer it a 404
        $this->assertFalse($this->rendered['deletable']);
    }

    // A session opened through a provider has no password it knows, so nothing to change
    public function testOffersNoPasswordChangeToAnOAuthSession(): void
    {
        $controller = $this->controller();
        $this->session->set(OAuthLoginController::SESSION_OAUTH_LOGIN, true);

        $controller->index($this->request());

        $this->assertNull($this->rendered['passwordForm']);
    }

    // A wrong current password changes nothing
    public function testAWrongCurrentPasswordChangesNothing(): void
    {
        $passwordResetter = $this->createMock(PasswordResetter::class);
        $passwordResetter->expects($this->never())->method('resetPassword');

        $response = $this->controller($passwordResetter)->index($this->submit('not-the-password'));

        $this->assertSame(422, $response->getStatusCode());
    }

    // The right current password has the new one hashed and saved, then the page is read again with a flash
    public function testTheRightCurrentPasswordChangesIt(): void
    {
        $passwordResetter = $this->createMock(PasswordResetter::class);
        $passwordResetter->expects($this->once())->method('resetPassword')->with($this->isInstanceOf(UserStub::class), self::NEW_PASSWORD);

        $response = $this->controller($passwordResetter)->index($this->submit(self::CURRENT_PASSWORD));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/en/account', $response->headers->get('Location'));
        $this->assertSame(['flash.password_changed'], $this->session->getFlashBag()->get('success'));
    }

    // A remembered session reads the page but does not rewrite the password
    public function testARememberedSessionCannotChangeIt(): void
    {
        $passwordResetter = $this->createMock(PasswordResetter::class);
        $passwordResetter->expects($this->never())->method('resetPassword');

        $this->expectException(AccessDeniedException::class);

        $this->controller($passwordResetter, fullyAuthenticated: false)->index($this->submit(self::CURRENT_PASSWORD));
    }

    // The page read in a language named in its url, which the negotiator never moves
    private function request(): Request
    {
        $request = Request::create('/en/account');
        $request->attributes->set('_locale', 'en');
        $request->setSession($this->session);

        return $request;
    }

    // The password change as the page posts it
    private function submit(string $currentPassword): Request
    {
        $request = Request::create('/en/account', 'POST', ['account_password' => [
            'currentPassword' => $currentPassword,
            'plainPassword' => ['first' => self::NEW_PASSWORD, 'second' => self::NEW_PASSWORD],
        ]]);
        $request->attributes->set('_locale', 'en');
        $request->setSession($this->session);

        return $request;
    }

    // A real form factory and validator, the member's password stored in plain text so UserPasswordValidator compares it as is
    /** @param iterable<AccountSectionProviderInterface> $providers */
    private function controller(?PasswordResetter $passwordResetter = null, iterable $providers = [], bool $fullyAuthenticated = true): AccountController
    {
        $user = new UserStub()->setPassword(self::CURRENT_PASSWORD);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            $this->rendered = $context;

            return 'page';
        });

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                'security.validator.user_password' => new UserPasswordValidator($tokenStorage, new PasswordHasherFactory([UserStub::class => ['algorithm' => 'plaintext']])),
                NotCompromisedPasswordValidator::class => new NotCompromisedPasswordValidator(null, 'UTF-8', false),
            ]))
            ->getValidator();
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();

        $siteLocales = new SiteLocales(['fr', 'en'], 'fr');
        $controller = new AccountController(
            new AccountSectionBuilder($providers),
            new LocalizedRouteNegotiator($siteLocales, new LocaleSwitcher('fr', []), $this->createStub(UrlGeneratorInterface::class)),
            $passwordResetter ?? $this->createStub(PasswordResetter::class),
            $siteLocales,
            $translator,
        );
        $controller->setContainer($this->createContainer([
            'form.factory' => $formFactory,
            'request_stack' => new RequestStack([$request]),
            'security.authorization_checker' => $fullyAuthenticated ? $this->createAuthorizationCheckerFor('IS_AUTHENTICATED_FULLY') : $this->createAuthorizationChecker(false),
            'security.token_storage' => $tokenStorage,
            'twig' => $twig,
        ]));

        return $controller;
    }
}
