<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

// Built-in FormActionInterface provider (key "send_email"), so a Form built entirely through the admin - no custom bundle/code - can still notify someone by email on submit. Configured via Form::$actionConfig: "to"/"toName"/"from"/"fromName"/"replyTo"/"replyToName"/"subject" (all optional, EmailService/ConfigService fill in the rest), "senderEmailField" (name of the submitted field holding the visitor's own email, used as replyTo) and "offerReceiveCopy" (shows a "receive a copy" checkbox, see FormSubmissionType - the visitor's own answer, not a fixed admin choice, decides whether a copy is actually sent). The email body is either "emailTemplate" (the name of an EmailTemplate, rendered by EmailTemplateRenderer with the submitted fields available to a TYPE_FIELDS_TABLE block - see UiBundle Readme) or, failing that/its lookup, the legacy "template" Twig path (defaults to DEFAULT_TEMPLATE)
class SendEmailFormAction implements FormActionInterface
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

        $html = $this->renderedHtml($config, $form, $labelledFields);

        $addressing = $this->addressing($config, $senderEmail);

        $request = new EmailSendRequest(
            // Same sentence as the heading the seeded EmailTemplate opens with ("Nouveau message via contact"), and translated like it: an admin reading their inbox got an English subject over a French email
            subject: $config['subject'] ?? $this->translator->trans('label.form_new_message', ['%form%' => (string) $form->getName()], 'ui'),
            context: ['form' => $form, 'fields' => $labelledFields],
            template: null === $html ? ($config['template'] ?? self::DEFAULT_TEMPLATE) : null,
            html: $html,
            from: $addressing['from'],
            fromName: $addressing['fromName'],
            to: $addressing['to'],
            toName: $addressing['toName'],
            replyTo: $addressing['replyTo'],
            replyToName: $addressing['replyToName'],
            // The visitor's own checkbox answer (see FormSubmissionType's "receiveCopy" field, only rendered when actionConfig's "offerReceiveCopy" is set) - not a fixed admin choice
            copyToEmail: (!empty($submittedData['receiveCopy']) && null !== $senderEmail) ? $senderEmail : null,
        );

        return $this->emailService->send($request);
    }

    // The body a named EmailTemplate composes, or null for a form that names none - which falls the request back on its Twig template
    private function renderedHtml(array $config, Form $form, array $labelledFields): ?string
    {
        $emailTemplate = isset($config['emailTemplate'])
            ? $this->emailTemplateRepository->findOneBy(['name' => $config['emailTemplate']])
            : null;

        if (null === $emailTemplate) {
            return null;
        }

        return $this->emailTemplateRenderer->render($emailTemplate, ['form_name' => (string) $form->getName(), 'fields' => $labelledFields]);
    }

    // Who the message is written to and from, each left to the mailer's own defaults when the form says nothing - the visitor's address answering for the reply-to
    // @return array{from: ?string, fromName: ?string, to: ?string, toName: ?string, replyTo: ?string, replyToName: ?string}
    private function addressing(array $config, ?string $senderEmail): array
    {
        return [
            'from' => $config['from'] ?? null,
            'fromName' => $config['fromName'] ?? null,
            'to' => $config['to'] ?? null,
            'toName' => $config['toName'] ?? null,
            'replyTo' => $config['replyTo'] ?? $senderEmail,
            'replyToName' => $config['replyToName'] ?? null,
        ];
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
