<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\DebugPreviewCapableInterface;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use c975L\UiBundle\Service\SendEmailFormAction;
use PHPUnit\Framework\TestCase;

class SendEmailFormActionTest extends TestCase
{
    // Both dependencies default to "no EmailTemplate found", so the legacy path stays exercised
    private function createAction(
        EmailService $emailService,
        ?EmailTemplateRepository $emailTemplateRepository = null,
        ?EmailTemplateRenderer $emailTemplateRenderer = null,
    ): SendEmailFormAction {
        $emailTemplateRepository ??= $this->createConfiguredStub(EmailTemplateRepository::class, ['findOneBy' => null]);
        $emailTemplateRenderer ??= $this->createStub(EmailTemplateRenderer::class);

        return new SendEmailFormAction($emailService, $emailTemplateRepository, $emailTemplateRenderer);
    }

    public function testImplementsDebugPreviewCapableInterface(): void
    {
        $this->assertInstanceOf(DebugPreviewCapableInterface::class, $this->createAction($this->createStub(EmailService::class)));
    }

    public function testConsumeDebugPreviewDelegatesToEmailService(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('consumeDebugPreview')->willReturn('<html>preview</html>');

        $action = $this->createAction($emailService);

        $this->assertSame('<html>preview</html>', $action->consumeDebugPreview());
    }

    private function buildForm(string $name, ?array $actionConfig, array $fields): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setActionConfig($actionConfig);
        foreach ($fields as $fieldName => $label) {
            $field = new FormField();
            $field->setName($fieldName);
            $field->setLabel($label);
            $form->addField($field);
        }

        return $form;
    }

    public function testGetKeyReturnsSendEmail(): void
    {
        $action = $this->createAction($this->createStub(EmailService::class));

        $this->assertSame('send_email', $action->getKey());
    }

    public function testHandleUsesDefaultSubjectAndTemplateWhenNoActionConfig(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $form = $this->buildForm('newsletter', null, ['email' => 'Email']);
        $action = $this->createAction($emailService);

        $result = $action->handle($form, ['email' => 'visitor@example.com']);

        $this->assertTrue($result);
        $this->assertSame('New submission: newsletter', $captured->subject);
        $this->assertSame('@c975LUi/emails/form_submission.html.twig', $captured->template);
        $this->assertNull($captured->html);
        $this->assertSame(['Email' => 'visitor@example.com'], $captured->context['fields']);
        $this->assertNull($captured->to);
        $this->assertNull($captured->copyToEmail);
    }

    // Only "name" is unique, so two fields sharing a label must not collapse onto one context key
    public function testHandleDisambiguatesDuplicateFieldLabels(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $form = $this->buildForm('contact', null, ['phone' => 'Phone', 'phone-2' => 'Phone']);
        $action = $this->createAction($emailService);

        $action->handle($form, ['phone' => '0600000000', 'phone-2' => '0700000000']);

        $this->assertSame(
            ['Phone' => '0600000000', 'Phone (2)' => '0700000000'],
            $captured->context['fields']
        );
    }

    public function testHandleUsesActionConfigOverrides(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $form = $this->buildForm('newsletter', [
            'to' => 'owner@example.com',
            'toName' => 'Owner',
            'subject' => 'Fixed subject',
            'template' => '@App/emails/custom.html.twig',
            'senderEmailField' => 'email',
            'offerReceiveCopy' => true,
        ], ['email' => 'Email']);
        $action = $this->createAction($emailService);

        // "receiveCopy" is the visitor's own checkbox answer (see FormSubmissionType), not a fixed admin choice
        $action->handle($form, ['email' => 'visitor@example.com', 'receiveCopy' => true]);

        $this->assertSame('owner@example.com', $captured->to);
        $this->assertSame('Owner', $captured->toName);
        $this->assertSame('Fixed subject', $captured->subject);
        $this->assertSame('@App/emails/custom.html.twig', $captured->template);
        $this->assertSame('visitor@example.com', $captured->replyTo);
        $this->assertSame('visitor@example.com', $captured->copyToEmail);
    }

    public function testHandleDoesNotSendCopyWhenCheckboxNotOffered(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $form = $this->buildForm('newsletter', ['senderEmailField' => 'email'], ['email' => 'Email']);
        $action = $this->createAction($emailService);

        $action->handle($form, ['email' => 'visitor@example.com']);

        $this->assertNull($captured->copyToEmail);
        // senderEmailField still feeds replyTo even without receiveCopy
        $this->assertSame('visitor@example.com', $captured->replyTo);
    }

    public function testHandleDoesNotSendCopyWhenCheckboxOfferedButLeftUnchecked(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $form = $this->buildForm('newsletter', ['senderEmailField' => 'email', 'offerReceiveCopy' => true], ['email' => 'Email']);
        $action = $this->createAction($emailService);

        $action->handle($form, ['email' => 'visitor@example.com', 'receiveCopy' => false]);

        $this->assertNull($captured->copyToEmail);
    }

    public function testHandleReturnsFalseWhenEmailServiceFails(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);

        $form = $this->buildForm('newsletter', null, []);
        $action = $this->createAction($emailService);

        $this->assertFalse($action->handle($form, []));
    }

    // A named EmailTemplate that exists wins over the legacy Twig "template" path
    public function testHandleUsesEmailTemplateWhenConfiguredAndFound(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $emailTemplate = new EmailTemplate();
        $emailTemplate->setName('contact_notification');

        $emailTemplateRepository = $this->createStub(EmailTemplateRepository::class);
        $emailTemplateRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => ['name' => 'contact_notification'] === $criteria ? $emailTemplate : null
        );

        $capturedRenderArgs = null;
        $emailTemplateRenderer = $this->createStub(EmailTemplateRenderer::class);
        $emailTemplateRenderer->method('render')->willReturnCallback(
            function (EmailTemplate $template, array $variables) use (&$capturedRenderArgs): string {
                $capturedRenderArgs = [$template, $variables];

                return '<p>Compiled</p>';
            }
        );

        $form = $this->buildForm('contact', ['emailTemplate' => 'contact_notification'], ['email' => 'Email']);
        $action = $this->createAction($emailService, $emailTemplateRepository, $emailTemplateRenderer);

        $action->handle($form, ['email' => 'visitor@example.com']);

        $this->assertNull($captured->template);
        $this->assertSame('<p>Compiled</p>', $captured->html);
        $this->assertSame([$emailTemplate, ['form_name' => 'contact', 'fields' => ['Email' => 'visitor@example.com']]], $capturedRenderArgs);
    }

    // A stale "emailTemplate" name must fall back to the legacy path, never break the send
    public function testHandleFallsBackToLegacyTemplateWhenEmailTemplateNameNotFound(): void
    {
        $captured = null;
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use (&$captured): bool {
            $captured = $request;

            return true;
        });

        $emailTemplateRepository = $this->createConfiguredStub(EmailTemplateRepository::class, ['findOneBy' => null]);

        $form = $this->buildForm('contact', ['emailTemplate' => 'does_not_exist'], ['email' => 'Email']);
        $action = $this->createAction($emailService, $emailTemplateRepository);

        $action->handle($form, ['email' => 'visitor@example.com']);

        $this->assertSame('@c975LUi/emails/form_submission.html.twig', $captured->template);
        $this->assertNull($captured->html);
    }
}
