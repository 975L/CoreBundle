<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\DebugPreviewCapableInterface;
use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

// Built-in FormActionInterface provider (key "send_email"), so a Form built entirely through the admin - no custom bundle/code - can still notify someone by email on submit. Configured via Form::$actionConfig: "to"/"toName"/"from"/"fromName"/"replyTo"/"replyToName"/"subject" (all optional, EmailService/ConfigService fill in the rest), "senderEmailField" (name of the submitted field holding the visitor's own email, used as replyTo) and "offerReceiveCopy" (shows a "receive a copy" checkbox, see FormSubmissionType - the visitor's own answer, not a fixed admin choice, decides whether a copy is actually sent). The email body is either "emailTemplate" (the name of an EmailTemplate, rendered by EmailTemplateRenderer with the submitted fields available to a TYPE_FIELDS_TABLE block - see UiBundle Readme) or, failing that/its lookup, the legacy "template" Twig path (defaults to DEFAULT_TEMPLATE)
class SendEmailFormAction implements FormActionInterface, DebugPreviewCapableInterface
{
    private const string DEFAULT_TEMPLATE = '@c975LUi/emails/form_submission.html.twig';

    public function __construct(
        private readonly EmailService $emailService,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKey(): string
    {
        return 'send_email';
    }

    public function handle(Form $form, array $submittedData): bool
    {
        $config = $form->getActionConfig() ?? [];

        $senderEmail = isset($config['senderEmailField']) ? ($submittedData[$config['senderEmailField']] ?? null) : null;
        $labelledFields = $this->labelledFields($form, $submittedData);

        $emailTemplate = isset($config['emailTemplate'])
            ? $this->emailTemplateRepository->findOneBy(['name' => $config['emailTemplate']])
            : null;
        $html = null !== $emailTemplate
            ? $this->emailTemplateRenderer->render($emailTemplate, ['form_name' => (string) $form->getName(), 'fields' => $labelledFields])
            : null;

        $request = new EmailSendRequest(
            // Same sentence as the heading the seeded EmailTemplate opens with ("Nouveau message via contact"), and translated like it: an admin reading their inbox got an English subject over a French email
            subject: $config['subject'] ?? $this->translator->trans('label.form_new_message', ['%form%' => (string) $form->getName()], 'ui'),
            context: ['form' => $form, 'fields' => $labelledFields],
            template: null === $html ? ($config['template'] ?? self::DEFAULT_TEMPLATE) : null,
            html: $html,
            from: $config['from'] ?? null,
            fromName: $config['fromName'] ?? null,
            to: $config['to'] ?? null,
            toName: $config['toName'] ?? null,
            replyTo: $config['replyTo'] ?? $senderEmail,
            replyToName: $config['replyToName'] ?? null,
            // The visitor's own checkbox answer (see FormSubmissionType's "receiveCopy" field, only rendered when actionConfig's "offerReceiveCopy" is set) - not a fixed admin choice
            copyToEmail: (!empty($submittedData['receiveCopy']) && null !== $senderEmail) ? $senderEmail : null,
        );

        return $this->emailService->send($request);
    }

    public function consumeDebugPreview(): ?string
    {
        return $this->emailService->consumeDebugPreview();
    }

    // A repeated label is disambiguated here, only "name" being unique, else one value would be lost
    private function labelledFields(Form $form, array $submittedData): array
    {
        $labelled = [];
        $labelCounts = [];
        foreach ($form->getFields() as $field) {
            $label = (string) $field->getLabel();
            $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
            $key = $labelCounts[$label] > 1 ? sprintf('%s (%d)', $label, $labelCounts[$label]) : $label;
            $labelled[$key] = $submittedData[$field->getName()] ?? null;
        }

        return $labelled;
    }
}
