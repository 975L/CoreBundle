<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Contract\RequiresAnonymousInterface;
use c975L\UiBundle\Controller\FormController;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\ExpressionEvaluator;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\FormPrefillHelper;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class FormControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createRequest(string $method = 'GET', ?string $referer = null): Request
    {
        $request = Request::create('/form/contact', $method);
        $request->setSession(new Session(new MockArraySessionStorage()));
        if (null !== $referer) {
            $request->headers->set('referer', $referer);
        }

        return $request;
    }

    private function createSubmittedForm(bool $submitted, bool $valid): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('getData')->willReturn([]);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    private function createFormFactory(FormInterface $form): \Symfony\Component\Form\FormFactoryInterface
    {
        $factory = $this->createStub(\Symfony\Component\Form\FormFactoryInterface::class);
        $factory->method('create')->willReturn($form);

        return $factory;
    }

    private function createBotProtection(bool $suspicious = false): FormBotProtection
    {
        $botProtection = $this->createStub(FormBotProtection::class);
        $botProtection->method('isSuspicious')->willReturn($suspicious);

        return $botProtection;
    }

    // Defaults to an anonymous visitor (no user), the only case every pre-existing test scenario assumes
    private function createSecurity(bool $authenticated = false): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($authenticated ? $this->createStub(UserInterface::class) : null);

        return $security;
    }

    // Every collaborator the controller takes, each one optional so a test only names the ones it cares about
    // #lizard forgives - no branch here: Lizard counts each nullable "?" and each "??" twice, reading eight optional parameters as CCN 24
    private function createController(
        FormInterface $form,
        ?FormRepository $formRepository = null,
        ?FormActionRegistry $actionRegistry = null,
        ?FormBotProtection $botProtection = null,
        ?RateLimiterGuard $rateLimiterGuard = null,
        ?FormPrefillHelper $prefillHelper = null,
        ?Security $security = null,
        ?Environment $twig = null,
        ?TranslatorInterface $translator = null,
    ): FormController {
        if (null === $translator) {
            $translator = $this->createStub(TranslatorInterface::class);
            $translator->method('trans')->willReturnArgument(0);
        }

        $rateLimiter = $rateLimiterGuard ?? $this->createStub(RateLimiterGuard::class);
        if (null === $rateLimiterGuard) {
            $rateLimiter->method('isAcceptedForIp')->willReturn(true);
        }

        $controller = new FormController(
            $formRepository ?? $this->createSubmittableFormRepository(),
            $actionRegistry ?? $this->createStub(FormActionRegistry::class),
            $botProtection ?? $this->createBotProtection(),
            $rateLimiter,
            $prefillHelper ?? $this->createStub(FormPrefillHelper::class),
            $this->createStub(ExpressionEvaluator::class),
            $translator,
            $security ?? $this->createSecurity(),
        );

        if (null === $twig) {
            $twig = $this->createStub(Environment::class);
            $twig->method('render')->willReturn('<form></form>');
        }
        $controller->setContainer($this->createContainer([
            'twig' => $twig,
            'form.factory' => $this->createFormFactory($form),
        ]));

        return $controller;
    }

    private function createSubmittableFormRepository(): FormRepository
    {
        $uiForm = new Form()->setName('contact')->setAction('send_email');

        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($uiForm);

        return $repository;
    }

    public function testFragmentThrowsNotFoundWhenFormDoesNotExist(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createSubmittedForm(false, false), $repository)
            ->fragment('unknown', $this->createRequest());
    }

    public function testFragmentThrowsNotFoundWhenFormHasNoAction(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn(new Form()->setName('contact'));

        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createSubmittedForm(false, false), $repository)
            ->fragment('contact', $this->createRequest());
    }

    public function testFragmentRendersTheFormFragment(): void
    {
        $response = $this->createController($this->createSubmittedForm(false, false))
            ->fragment('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<form></form>', $response->getContent());
    }

    private function createCalculatorRepository(): FormRepository
    {
        $uiForm = new Form()->setName('economies');
        $uiForm->addOutput(new FormOutput()->setLabel('Total')->setName('total')->setExpression('1'));

        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($uiForm);

        return $repository;
    }

    // A calculator has no action on purpose: it computes and displays instead of submitting, so the "no action" gate that 404s an unfinished Form must let it through
    public function testFragmentRendersTheCalculatorTemplateForAFormThatOwnsOutputs(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@c975LUi/components/Form/Calculator.html.twig', $this->anything())
            ->willReturn('<div class="ui-calculator"></div>');

        $response = $this->createController($this->createSubmittedForm(false, false), $this->createCalculatorRepository(), twig: $twig)
            ->fragment('economies', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    // Minting a CSRF token starts a session, which is the very cost skipping the timer is there to avoid - and a calculator posts nothing for a token to protect
    public function testACalculatorIsBuiltWithNeitherProtectionsNorACsrfToken(): void
    {
        $captured = [];
        $factory = $this->createStub(\Symfony\Component\Form\FormFactoryInterface::class);
        $factory->method('create')->willReturnCallback(
            function (string $type, mixed $data, array $options) use (&$captured): FormInterface {
                $captured = $options;

                return $this->createSubmittedForm(false, false);
            }
        );

        $controller = $this->createController($this->createSubmittedForm(false, false), $this->createCalculatorRepository());
        $controller->setContainer($this->createContainer([
            'twig' => $this->createConfiguredStub(Environment::class, ['render' => '<div></div>']),
            'form.factory' => $factory,
        ]));

        $controller->fragment('economies', $this->createRequest());

        $this->assertFalse($captured['protections']);
        $this->assertFalse($captured['csrf_protection']);
    }

    // startTimer() writes to the session, and a calculator has no submission to time - every visitor of a cached page would pay a session cookie for nothing
    public function testFragmentNeverStartsABotTimerForACalculator(): void
    {
        $botProtection = $this->createMock(FormBotProtection::class);
        $botProtection->expects($this->never())->method('startTimer');

        $this->createController($this->createSubmittedForm(false, false), $this->createCalculatorRepository(), botProtection: $botProtection)
            ->fragment('economies', $this->createRequest());
    }

    // Landed on directly (a shared link), a calculator is the same thing wrapped in the standalone page shell - and still never submitted
    public function testSubmitWrapsACalculatorInTheStandalonePageShellWithoutHandlingAnything(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@c975LUi/form/page.html.twig', $this->anything())
            ->willReturn('<html></html>');

        $response = $this->createController($this->createSubmittedForm(false, false), $this->createCalculatorRepository(), twig: $twig)
            ->submit('economies', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    // A disabled Form (see Form::$enabled - lets an admin pause it without unpublishing its Page or clearing "action") shows a notice instead of the form, on both the Block-embedded fragment and the bare submit route
    public function testFragmentRendersDisabledNoticeWhenFormIsDisabled(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn(new Form()->setName('contact')->setAction('send_email')->setEnabled(false));

        $response = $this->createController($this->createSubmittedForm(false, false), $repository)
            ->fragment('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<form></form>', $response->getContent());
    }

    // RegisterFormAction/ResetPasswordRequestFormAction (scaffold) implement RequiresAnonymousInterface - an already-authenticated visitor gets a notice instead of the form, on both routes
    private function createRequiresAnonymousActionRegistry(string $key): FormActionRegistry
    {
        $action = new readonly class ($key) implements FormActionInterface, RequiresAnonymousInterface {
            public function __construct(private string $key)
            {
            }

            public function getKey(): string
            {
                return $this->key;
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };

        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('has')->willReturn(true);
        $actionRegistry->method('get')->willReturn($action);

        return $actionRegistry;
    }

    public function testFragmentRendersAlreadyAuthenticatedNoticeWhenActionRequiresAnonymousAndUserIsLoggedIn(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn(new Form()->setName('register')->setAction('register'));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@c975LUi/components/Form/FormAlreadyAuthenticated.html.twig')
            ->willReturn('<already-authenticated>');

        $response = $this->createController(
            $this->createSubmittedForm(false, false),
            $repository,
            actionRegistry: $this->createRequiresAnonymousActionRegistry('register'),
            security: $this->createSecurity(true),
            twig: $twig,
        )->fragment('register', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<already-authenticated>', $response->getContent());
    }

    // Same notice as fragment() above, but wrapped in the standalone page shell - "ui_form_submit" is an address a visitor lands on, not a fragment embedded in an already-rendered page
    public function testSubmitRendersAlreadyAuthenticatedNoticeWithoutHandlingAnySubmissionWhenActionRequiresAnonymousAndUserIsLoggedIn(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn(new Form()->setName('register')->setAction('register'));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@c975LUi/form/page.html.twig', $this->callback(
                static fn (array $parameters): bool => '@c975LUi/components/Form/FormAlreadyAuthenticated.html.twig' === $parameters['innerTemplate']
            ))
            ->willReturn('<already-authenticated>');

        $response = $this->createController(
            $this->createSubmittedForm(true, true),
            $repository,
            actionRegistry: $this->createRequiresAnonymousActionRegistry('register'),
            security: $this->createSecurity(true),
            twig: $twig,
        )->submit('register', $this->createRequest('POST', 'http://localhost/page'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<already-authenticated>', $response->getContent());
    }

    // The bare route is a page of its own: served as a fragment it carried neither the stylesheet hiding the honeypot field nor the importmap the "captcha" Stimulus controller needs to fill its token, which CaptchaValidator then rejected as a bot. Never indexed either, the Page carrying the "form" Block being the canonical address
    public function testSubmitWrapsTheFormInTheStandalonePageShell(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@c975LUi/form/page.html.twig', $this->callback(
                static fn (array $parameters): bool => '@c975LUi/components/Form/Form.html.twig' === $parameters['innerTemplate']
                    && 'noindex, follow' === $parameters['robots']
                    && isset($parameters['form'])
            ))
            ->willReturn('<page></page>');

        $response = $this->createController($this->createSubmittedForm(false, false), twig: $twig)
            ->submit('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<page></page>', $response->getContent());
    }

    // The fragment stays bare, being embedded in an already-rendered page (see FormBlock.html.twig) - a layout there would nest a second <html> inside the host page
    public function testFragmentRendersTheBareFormWithoutAnyPageShell(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@c975LUi/components/Form/Form.html.twig')
            ->willReturn('<form></form>');

        $response = $this->createController($this->createSubmittedForm(false, false), twig: $twig)
            ->fragment('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<form></form>', $response->getContent());
    }

    // An action not implementing RequiresAnonymousInterface (e.g. "contact") stays open to a logged-in visitor
    public function testFragmentRendersTheFormFragmentEvenWhenAuthenticatedIfActionDoesNotRequireAnonymous(): void
    {
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'send_email';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('has')->willReturn(true);
        $actionRegistry->method('get')->willReturn($action);

        $response = $this->createController(
            $this->createSubmittedForm(false, false),
            actionRegistry: $actionRegistry,
            security: $this->createSecurity(true),
        )->fragment('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<form></form>', $response->getContent());
    }

    public function testSubmitRendersDisabledNoticeWhenFormIsDisabledWithoutHandlingAnySubmission(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn(new Form()->setName('contact')->setAction('send_email')->setEnabled(false));
        $actionRegistry = $this->createMock(FormActionRegistry::class);
        $actionRegistry->expects($this->never())->method('get');

        $response = $this->createController(
            $this->createSubmittedForm(true, true),
            $repository,
            actionRegistry: $actionRegistry,
        )->submit('contact', $this->createRequest('POST', 'http://localhost/page'));

        $this->assertSame(200, $response->getStatusCode());
    }

    // Regression guard: a stale/unregistered action key (e.g. a typo, or a provider removed) must not break the GET display - only submitting it fails, same as before this capability existed
    public function testFragmentRendersNormallyWhenActionKeyIsUnregisteredEvenWhenUserIsLoggedIn(): void
    {
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('has')->willReturn(false);

        $response = $this->createController(
            $this->createSubmittedForm(false, false),
            actionRegistry: $actionRegistry,
            security: $this->createSecurity(true),
        )->fragment('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<form></form>', $response->getContent());
    }

    public function testSubmitRendersWithoutHandlingWhenNotSubmitted(): void
    {
        $response = $this->createController($this->createSubmittedForm(false, false))
            ->submit('contact', $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSubmitRedirectsToRefererWithoutCallingAnyActionWhenBotIsSuspicious(): void
    {
        $actionRegistry = $this->createMock(FormActionRegistry::class);
        $actionRegistry->expects($this->never())->method('get');

        $response = $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            botProtection: $this->createBotProtection(true),
        )->submit('contact', $this->createRequest('POST', 'http://localhost/page'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/page', $response->headers->get('location'));
    }

    public function testSubmitFlashesWarningWhenRateLimited(): void
    {
        $rateLimiterGuard = $this->createStub(RateLimiterGuard::class);
        $rateLimiterGuard->method('isAcceptedForIp')->willReturn(false);
        $actionRegistry = $this->createMock(FormActionRegistry::class);
        $actionRegistry->expects($this->never())->method('get');

        $request = $this->createRequest('POST', 'http://localhost/page');
        $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            rateLimiterGuard: $rateLimiterGuard,
        )->submit('contact', $request);

        $this->assertTrue($request->getSession()->getFlashBag()->has('warning'));
    }

    // An unresolved client IP must fail open, not share one bucket with every other such visitor
    public function testSubmitSkipsRateLimitingWhenClientIpIsUnresolved(): void
    {
        $rateLimiterGuard = $this->createMock(RateLimiterGuard::class);
        $rateLimiterGuard->expects($this->never())->method('isAcceptedForIp');
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'send_email';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('get')->willReturn($action);

        $request = $this->createRequest('POST');
        $request->server->remove('REMOTE_ADDR');

        $response = $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            rateLimiterGuard: $rateLimiterGuard,
        )->submit('contact', $request);

        $this->assertFalse($request->getSession()->getFlashBag()->has('warning'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSubmitClearsPrefillAndFlashesSuccessWhenActionSucceeds(): void
    {
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'send_email';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('get')->willReturn($action);

        $prefillHelper = $this->createMock(FormPrefillHelper::class);
        $prefillHelper->expects($this->once())->method('clear')->with($this->anything(), 'contact');

        $request = $this->createRequest('POST', 'http://localhost/page');
        $response = $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            prefillHelper: $prefillHelper,
        )->submit('contact', $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($request->getSession()->getFlashBag()->has('success'));
    }

    // Regression guard: the redirect-to-referer path lands on the site layout, which renders flashes as-is - an untranslated key would be shown verbatim to the visitor
    public function testSubmitFlashesTheTranslatedMessageNotTheRawKey(): void
    {
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'send_email';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('get')->willReturn($action);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => 'ui' === $domain && 'label.form_message_sent' === $id
                ? 'Votre message a bien été envoyé.'
                : $id
        );

        $request = $this->createRequest('POST', 'http://localhost/page');
        $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            translator: $translator,
        )->submit('contact', $request);

        $this->assertSame(
            ['Votre message a bien été envoyé.'],
            $request->getSession()->getFlashBag()->get('success')
        );
    }

    // The registration flash is the only place a visitor ever learns that a confirmation email may not have left: EmailVerifier holds an address for an hour after writing to it and answers false without sending anything
    public function testSubmitFlashesTheRegistrationWordingOnARegistration(): void
    {
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'register';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('get')->willReturn($action);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => 'ui' === $domain && 'label.form_registered' === $id
                ? 'Merci, votre inscription a bien été reçue.'
                : $id
        );

        $request = $this->createRequest('POST', 'http://localhost/page');
        $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            translator: $translator,
        )->submit('register', $request);

        $this->assertSame(
            ['Merci, votre inscription a bien été reçue.'],
            $request->getSession()->getFlashBag()->get('success')
        );
    }

    // Regression guard: Referer is client-supplied - redirecting there unchecked is an open redirect
    public function testSubmitDoesNotRedirectToACrossOriginReferer(): void
    {
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'send_email';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return true;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('get')->willReturn($action);

        $response = $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
        )->submit('contact', $this->createRequest('POST', 'https://evil.example.com/phishing'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSubmitDoesNotClearPrefillAndFlashesDangerWhenActionFails(): void
    {
        $action = new class implements FormActionInterface {
            public function getKey(): string
            {
                return 'send_email';
            }

            public function handle(Form $form, array $submittedData): bool
            {
                return false;
            }
        };
        $actionRegistry = $this->createStub(FormActionRegistry::class);
        $actionRegistry->method('get')->willReturn($action);

        $prefillHelper = $this->createMock(FormPrefillHelper::class);
        $prefillHelper->expects($this->never())->method('clear');

        $request = $this->createRequest('POST', 'http://localhost/page');
        $this->createController(
            $this->createSubmittedForm(true, true),
            actionRegistry: $actionRegistry,
            prefillHelper: $prefillHelper,
        )->submit('contact', $request);

        $this->assertTrue($request->getSession()->getFlashBag()->has('danger'));
    }
}
