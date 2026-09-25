<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Contract\RequiresAnonymousInterface;
use c975L\UiBundle\Contract\SuccessUrlFormActionInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Form\FormSubmissionType;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\ExpressionEvaluator;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\FormPrefillHelper;
use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// Public entry point rendering/handling any c975L\UiBundle\Entity\Form (see FormPickerType/FormBlock.html.twig for the "form" Block kind embedding it) - same honeypot/timing/GDPR/recaptcha protection every c975L bundle's own public form already shares (see FormSubmissionType), plus a single shared rate limiter for every generic Form ("limiter.ui_form", optional - a Form built through the admin can't be bound to its own dedicated named DI service the way ContactFormBundle's contact form is)
class FormController extends AbstractController
{
    public function __construct(
        private readonly FormRepository $formRepository,
        private readonly FormActionRegistry $actionRegistry,
        private readonly FormBotProtection $botProtection,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly FormPrefillHelper $prefillHelper,
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ?RateLimiterFactoryInterface $formLimiterFactory = null,
    ) {
    }

    private function loadForm(string $name): Form
    {
        $form = $this->formRepository->findOneBy(['name' => $name]);
        // A calculator may have no action (see Form::isCalculator()) - it then computes and displays rather than submitting, so "has an action" alone would 404 every one of them
        if (null === $form || (null === $form->getAction() && !$form->isCalculator())) {
            throw new NotFoundHttpException(sprintf('No renderable Form named "%s"', $name));
        }

        return $form;
    }

    // Read off the request rather than through addFlash(), which reaches for the container's request_stack: the flash bag is carried by the concrete session, not declared on the interface the request hands back
    private function addFlashTo(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function sessionKeyFor(Form $uiForm): string
    {
        return 'ui_form_' . $uiForm->getName() . '_started_at';
    }

    // A stale/unregistered action key is left to fail at submit time as before (see FormActionRegistry::get()) - only a resolvable RequiresAnonymousInterface provider blocks the GET/POST paths here
    private function isBlockedForCurrentUser(Form $uiForm): bool
    {
        if (null === $this->security->getUser() || null === $uiForm->getAction() || !$this->actionRegistry->has($uiForm->getAction())) {
            return false;
        }

        return $this->actionRegistry->get($uiForm->getAction()) instanceof RequiresAnonymousInterface;
    }

    // "ui_form_submit" is a page a visitor can land on (a shared link, or a login page's "forgot password" on a site with no Page carrying the matching "form" Block - see FormUrlExtension), so everything it renders goes through a layout, unlike "ui_form_fragment", which is embedded in an already-rendered page and stays bare.
    // Never indexed: whichever Page carries the Block is the canonical address of that form, this route only the fallback - both layouts read "robots"
    private function renderPage(Form $uiForm, string $innerTemplate, array $parameters = []): Response
    {
        return $this->render('@c975LUi/form/page.html.twig', array_merge($parameters, [
            'innerTemplate' => $innerTemplate,
            'uiForm' => $uiForm,
            'robots' => 'noindex, follow',
        ]));
    }

    // A calculator given no action only computes and displays - one given an action is also sent, like any other Form, its results travelling with it (see SendEmailFormAction)
    private function isComputeOnly(Form $uiForm): bool
    {
        return $uiForm->isCalculator() && null === $uiForm->getAction();
    }

    private function buildSymfonyForm(Form $uiForm, array $prefill = []): FormInterface
    {
        $config = $uiForm->getActionConfig() ?? [];

        return $this->createForm(FormSubmissionType::class, null, [
            'fields' => $uiForm->getFields(),
            'offerReceiveCopy' => !empty($config['offerReceiveCopy']),
            'prefill' => $prefill,
            'protections' => !$this->isComputeOnly($uiForm),
            // A compute-only calculator posts nothing, so the token would protect nothing - and minting one starts a session, which is exactly the cost skipping startTimer() below is there to avoid: every visitor of a cached page carrying a calculator would pay a session cookie for a hidden field never sent back
            'csrf_protection' => !$this->isComputeOnly($uiForm),
        ]);
    }

    // The template a submittable Form renders through, and what it needs: a calculator sent along with its results carries them, computed from what the visitor typed rather than from the defaults, so a failed validation re-renders the numbers they had
    private function submittableView(Form $uiForm, FormInterface $symfonyForm): array
    {
        if (!$uiForm->isCalculator()) {
            return ['@c975LUi/components/Form/Form.html.twig', ['form' => $symfonyForm->createView()]];
        }

        return ['@c975LUi/components/Form/Calculator.html.twig', [
            'form' => $symfonyForm->createView(),
            'results' => $this->expressionEvaluator->compute($uiForm, (array) ($symfonyForm->getData() ?? [])),
        ]];
    }

    #[Route('/form/{name}/fragment', name: 'ui_form_fragment', methods: ['GET'])]
    public function fragment(string $name, Request $request): Response
    {
        $uiForm = $this->loadForm($name);
        if (!$uiForm->isEnabled()) {
            return $this->render('@c975LUi/components/Form/FormDisabled.html.twig', ['uiForm' => $uiForm]);
        }
        if ($this->isBlockedForCurrentUser($uiForm)) {
            return $this->render('@c975LUi/components/Form/FormAlreadyAuthenticated.html.twig', ['uiForm' => $uiForm]);
        }

        // A compute-only calculator never submits, so there is no submission to time - and startTimer() writes to the session, which would cost every visitor of a cached page a session cookie for nothing
        if ($this->isComputeOnly($uiForm)) {
            return $this->renderCalculator($uiForm);
        }

        $this->botProtection->startTimer($request, $this->sessionKeyFor($uiForm));

        [$template, $parameters] = $this->submittableView($uiForm, $this->buildSymfonyForm($uiForm, $this->prefillHelper->consume($request, $uiForm->getName())));

        return $this->render($template, ['uiForm' => $uiForm] + $parameters);
    }

    // Rendered with the results its own default values already give, so the page is right before a single keystroke - and stays right with no JavaScript at all, only frozen on those defaults
    private function renderCalculator(Form $uiForm): Response
    {
        return $this->render('@c975LUi/components/Form/Calculator.html.twig', [
            'uiForm' => $uiForm,
            'form' => $this->buildSymfonyForm($uiForm)->createView(),
            'results' => $this->expressionEvaluator->compute($uiForm, []),
        ]);
    }

    // What an accepted submission is worth: the action behind the form, unless the caller has already had their share of attempts. Returns where a success sends the visitor, when the action or the Form says so - null leaves them on the page they came from
    private function runAction(Form $uiForm, mixed $data, Request $request): ?string
    {
        // Fails open with no client IP, rather than lumping every such visitor onto one shared bucket.
        // Counted per caller and not per address, an IPv6 subscriber holding a block far larger than any ceiling could count - see RateLimiterGuard::isAcceptedForIp()
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->formLimiterFactory, $clientIp)) {
            $this->addFlashTo($request, 'warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));

            return null;
        }

        $action = $this->actionRegistry->get($uiForm->getAction());
        $success = $action->handle($uiForm, $data);

        // Only clear on an actual success - a failed action leaves the prefill in place, same resilience a "?s=..." query string would naturally have on a retry
        if ($success) {
            $this->prefillHelper->clear($request, $uiForm->getName());
        }

        // A page of its own says the submission went through better than a flash would, and saying it twice reads as a stutter
        $successUrl = $success ? $this->successUrl($uiForm, $action) : null;
        if (null !== $successUrl) {
            return $successUrl;
        }

        // A form that emails its submission (contact and the like) says so - "your message has been sent"; a registration says that its confirmation email may not have left, EmailVerifier holding an address for an hour after writing to it and this flash being the only thing the visitor ever reads back; every other action keeps the generic wording, a password reset request being no message sent by the visitor
        $successKey = match ($action->getKey()) {
            'send_email' => 'label.form_message_sent',
            'register' => 'label.form_registered',
            default => 'label.form_submitted',
        };

        // Translated here, not in the template: the redirect-to-referer path lands on the site layout, which renders flashes as-is
        $this->addFlashTo(
            $request,
            $success ? 'success' : 'danger',
            $this->translator->trans($success ? $successKey : 'label.form_submission_failed', [], 'ui')
        );

        return null;
    }

    // The seconds this form must at least take to fill in, when its action config sets its own rather than the site-wide "site-form-delay" - a form holding a single pasted field is filled in well under the default
    private function minDelay(Form $uiForm): ?int
    {
        $minDelay = $uiForm->getActionConfig()['minDelay'] ?? null;

        return is_int($minDelay) && $minDelay >= 0 ? $minDelay : null;
    }

    // The page a success leads to: the one the action has just created first, then the one the Form names in its "successUrl" (a thank-you page, typically), set by an editor in the action's JSON config
    private function successUrl(Form $uiForm, FormActionInterface $action): ?string
    {
        if ($action instanceof SuccessUrlFormActionInterface) {
            $url = $action->getSuccessUrl();
            if (null !== $url) {
                return $url;
            }
        }

        $url = $uiForm->getActionConfig()['successUrl'] ?? null;

        return is_string($url) && '' !== trim($url) ? $url : null;
    }

    // Where the visitor came from, when that is this very site - Referer is client-supplied, and an unchecked redirect there is an open redirect
    private function sameOriginReferer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        return null !== $referer && parse_url($referer, PHP_URL_HOST) === $request->getHost() ? $referer : null;
    }

    #[Route('/form/{name}', name: 'ui_form_submit', methods: ['GET', 'POST'])]
    public function submit(string $name, Request $request): Response
    {
        $uiForm = $this->loadForm($name);
        if (!$uiForm->isEnabled()) {
            return $this->renderPage($uiForm, '@c975LUi/components/Form/FormDisabled.html.twig');
        }
        if ($this->isBlockedForCurrentUser($uiForm)) {
            return $this->renderPage($uiForm, '@c975LUi/components/Form/FormAlreadyAuthenticated.html.twig');
        }

        // Same page as the Block would embed, just wrapped in a layout - a compute-only calculator has no action to run, so there is nothing here to submit, rate-limit or flash
        if ($this->isComputeOnly($uiForm)) {
            return $this->render('@c975LUi/form/page.html.twig', [
                'innerTemplate' => '@c975LUi/components/Form/Calculator.html.twig',
                'uiForm' => $uiForm,
                'robots' => 'noindex, follow',
                'form' => $this->buildSymfonyForm($uiForm)->createView(),
                'results' => $this->expressionEvaluator->compute($uiForm, []),
            ]);
        }

        $this->botProtection->startTimer($request, $this->sessionKeyFor($uiForm));

        $symfonyForm = $this->buildSymfonyForm($uiForm, $this->prefillHelper->consume($request, $uiForm->getName()));

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same redirect and no hint
        $suspicious = $request->isMethod('POST')
            && $this->botProtection->isSuspicious($request, $symfonyForm->getName(), $this->sessionKeyFor($uiForm), $this->minDelay($uiForm));

        if (!$suspicious) {
            $symfonyForm->handleRequest($request);
        }

        $accepted = $symfonyForm->isSubmitted() && $symfonyForm->isValid();

        if (!$suspicious && $accepted) {
            $successUrl = $this->runAction($uiForm, $symfonyForm->getData(), $request);
            if (null !== $successUrl) {
                return $this->redirect($successUrl);
            }
        }

        if ($suspicious || $accepted) {
            $referer = $this->sameOriginReferer($request);
            if (null !== $referer) {
                return $this->redirect($referer);
            }
        }

        [$template, $parameters] = $this->submittableView($uiForm, $symfonyForm);

        return $this->renderPage($uiForm, $template, $parameters);
    }
}
