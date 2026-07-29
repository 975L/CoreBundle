<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Contract\DebugPreviewCapableInterface;
use c975L\UiBundle\Contract\RequiresAnonymousInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Form\FormSubmissionType;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\FormPrefillHelper;
use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ?RateLimiterFactoryInterface $formLimiterFactory = null,
    ) {
    }

    private function loadForm(string $name): Form
    {
        $form = $this->formRepository->findOneBy(['name' => $name]);
        if (null === $form || null === $form->getAction()) {
            throw new NotFoundHttpException(sprintf('No submittable Form named "%s"', $name));
        }

        return $form;
    }

    private function sessionKeyFor(Form $uiForm): string
    {
        return 'ui_form_' . $uiForm->getName() . '_started_at';
    }

    // A stale/unregistered action key is left to fail at submit time as before (see FormActionRegistry::get()) - only a resolvable RequiresAnonymousInterface provider blocks the GET/POST paths here
    private function alreadyAuthenticatedResponse(Form $uiForm): ?Response
    {
        if (null === $this->security->getUser() || !$this->actionRegistry->has($uiForm->getAction())) {
            return null;
        }

        if (!$this->actionRegistry->get($uiForm->getAction()) instanceof RequiresAnonymousInterface) {
            return null;
        }

        return $this->render('@c975LUi/components/Form/FormAlreadyAuthenticated.html.twig', [
            'uiForm' => $uiForm,
        ]);
    }

    private function buildSymfonyForm(Form $uiForm, array $prefill = []): FormInterface
    {
        $config = $uiForm->getActionConfig() ?? [];

        return $this->createForm(FormSubmissionType::class, null, [
            'fields' => $uiForm->getFields(),
            'offerReceiveCopy' => !empty($config['offerReceiveCopy']),
            'prefill' => $prefill,
        ]);
    }

    #[Route('/form/{name}/fragment', name: 'ui_form_fragment', methods: ['GET'])]
    public function fragment(string $name, Request $request): Response
    {
        $uiForm = $this->loadForm($name);
        if (!$uiForm->isEnabled()) {
            return $this->render('@c975LUi/components/Form/FormDisabled.html.twig', ['uiForm' => $uiForm]);
        }
        if (null !== $response = $this->alreadyAuthenticatedResponse($uiForm)) {
            return $response;
        }

        $this->botProtection->startTimer($request, $this->sessionKeyFor($uiForm));

        return $this->render('@c975LUi/components/Form/Form.html.twig', [
            'uiForm' => $uiForm,
            'form' => $this->buildSymfonyForm($uiForm, $this->prefillHelper->consume($request, $uiForm->getName()))->createView(),
        ]);
    }

    #[Route('/form/{name}', name: 'ui_form_submit', methods: ['GET', 'POST'])]
    public function submit(string $name, Request $request): Response
    {
        $uiForm = $this->loadForm($name);
        if (!$uiForm->isEnabled()) {
            return $this->render('@c975LUi/components/Form/FormDisabled.html.twig', ['uiForm' => $uiForm]);
        }
        if (null !== $response = $this->alreadyAuthenticatedResponse($uiForm)) {
            return $response;
        }

        $this->botProtection->startTimer($request, $this->sessionKeyFor($uiForm));

        $symfonyForm = $this->buildSymfonyForm($uiForm, $this->prefillHelper->consume($request, $uiForm->getName()));

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same redirect and no hint
        $suspicious = $request->isMethod('POST')
            && $this->botProtection->isSuspicious($request, $symfonyForm->getName(), $this->sessionKeyFor($uiForm));

        if (!$suspicious) {
            $symfonyForm->handleRequest($request);
        }

        if (!$suspicious && $symfonyForm->isSubmitted() && $symfonyForm->isValid()) {
            // Fails open with no client IP, rather than lumping every such visitor onto one shared bucket
            $clientIp = $request->getClientIp();
            if (null !== $clientIp && !$this->rateLimiterGuard->isAccepted($this->formLimiterFactory, $clientIp)) {
                $request->getSession()->getFlashBag()->add('warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));
            } else {
                $action = $this->actionRegistry->get($uiForm->getAction());
                $success = $action->handle($uiForm, $symfonyForm->getData());

                // Only clear on an actual success - a failed action leaves the prefill in place, same resilience a "?s=..." query string would naturally have on a retry
                if ($success) {
                    $this->prefillHelper->clear($request, $uiForm->getName());
                }

                // e.g. SendEmailFormAction renders instead of actually sending in debug mode (ROLE_SUPER_ADMIN + "email-debug") - show that instead of the usual flash+redirect, or debug mode would otherwise look identical to a real send with no way to inspect it
                if ($action instanceof DebugPreviewCapableInterface) {
                    $debugPreview = $action->consumeDebugPreview();
                    if (null !== $debugPreview) {
                        return new Response($debugPreview);
                    }
                }

                $request->getSession()->getFlashBag()->add(
                    $success ? 'success' : 'danger',
                    $success ? 'label.form_submitted' : 'label.form_submission_failed'
                );
            }
        }

        if ($suspicious || ($symfonyForm->isSubmitted() && $symfonyForm->isValid())) {
            // Same-origin check: Referer is client-supplied, an unchecked redirect there is an open redirect
            $referer = $request->headers->get('referer');
            if (null !== $referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
                return $this->redirect($referer);
            }
        }

        return $this->render('@c975LUi/components/Form/Form.html.twig', [
            'uiForm' => $uiForm,
            'form' => $symfonyForm->createView(),
        ]);
    }
}
