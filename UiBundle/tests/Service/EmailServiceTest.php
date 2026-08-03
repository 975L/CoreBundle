<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use c975L\UiBundle\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;

// Moved from ContactFormBundle alongside the class it tests, generalized from ContactForm/ContactFormEvent to the bundle-agnostic EmailSendRequest - see UPGRADE.md
class EmailServiceTest extends TestCase
{
    // Builds a MailerInterface double that records every message it is asked to send
    private function createRecordingMailer(): object
    {
        return new class implements MailerInterface {
            /** @var TemplatedEmail[] */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
    }

    // Builds an EmailService bound to the given mailer/config/security/twig behaviour
    private function createService(
        object $mailer,
        array $configValues = [],
        bool $isSuperAdmin = false,
        string $renderedHtml = '',
        ?EmailLayoutRegistry $emailLayoutRegistry = null,
    ): EmailService {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(
            static fn (string $parameter) => \array_key_exists($parameter, $configValues)
        );
        $configService->method('get')->willReturnCallback(
            static fn (string $parameter) => $configValues[$parameter] ?? null
        );
        $configService->method('getBool')->willReturnCallback(
            static fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
        );

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isSuperAdmin);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn($renderedHtml);

        return new EmailService($configService, $mailer, $twig, $emailLayoutRegistry ?? new EmailLayoutRegistry(), $security);
    }

    public function testSendBuildsEmailFromRequestAndCallsMailerOnce(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $request = new EmailSendRequest(
            subject: 'Hello',
            context: [],
            template: 'emails/test.html.twig',
            from: 'from@example.com',
            to: 'to@example.com',
            replyTo: 'visitor@example.com',
        );

        $result = $service->send($request);

        $this->assertTrue($result);
        $this->assertCount(1, $mailer->sent);
        $sentEmail = $mailer->sent[0];
        $this->assertSame('Hello', $sentEmail->getSubject());
        $this->assertSame('from@example.com', $sentEmail->getFrom()[0]->getAddress());
        $this->assertSame('to@example.com', $sentEmail->getTo()[0]->getAddress());
        $this->assertSame('visitor@example.com', $sentEmail->getReplyTo()[0]->getAddress());
    }

    public function testSendFallsBackToConfigServiceWhenFromToNotGiven(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer, [
            'email-from' => 'config-from@example.com',
            'email-to' => 'config-to@example.com',
        ]);

        $service->send(new EmailSendRequest(subject: 'Hello', context: [], template: 'emails/test.html.twig'));

        $sentEmail = $mailer->sent[0];
        $this->assertSame('config-from@example.com', $sentEmail->getFrom()[0]->getAddress());
        $this->assertSame('config-to@example.com', $sentEmail->getTo()[0]->getAddress());
    }

    public function testSendSendsCopyToCopyToEmailAddress(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $request = new EmailSendRequest(
            subject: 'Hello',
            context: [],
            template: 'emails/test.html.twig',
            from: 'from@example.com',
            to: 'to@example.com',
            replyTo: 'siteowner@example.com',
            copyToEmail: 'visitor@example.com',
        );

        $service->send($request);

        $this->assertCount(2, $mailer->sent);
        $copy = $mailer->sent[1];
        $this->assertSame('visitor@example.com', $copy->getTo()[0]->getAddress());
        $this->assertFalse($copy->getHeaders()->has('Reply-To'));
    }

    public function testSendReturnsFalseAndRecordsErrorWhenMailerThrows(): void
    {
        $mailer = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('SMTP connection refused');
            }
        };
        $service = $this->createService($mailer);

        $request = new EmailSendRequest(subject: 'Hello', context: [], template: 'emails/test.html.twig', from: 'from@example.com', to: 'to@example.com');
        $result = $service->send($request);

        $this->assertFalse($result);
        $this->assertSame('SMTP connection refused', $service->getLastError());
    }

    public function testSendStashesRenderedHtmlAsDebugPreviewAndDoesNotSendEmailWhenDebugModeEnabledForSuperAdmin(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService(
            $mailer,
            ['email-debug' => 'true'],
            isSuperAdmin: true,
            renderedHtml: '<html><body><p>Rendered email</p></body></html>',
        );

        $request = new EmailSendRequest(subject: 'Hello', context: [], template: 'emails/test.html.twig', from: 'from@example.com', to: 'to@example.com');

        // No output must be echoed mid-request, as that would break header/cookie sending on the redirect that follows
        ob_start();
        $result = $service->send($request);
        $output = ob_get_clean();
        $this->assertSame('', $output);

        $this->assertTrue($result);
        $this->assertCount(0, $mailer->sent);

        $preview = $service->consumeDebugPreview();
        $this->assertNotNull($preview);
        $this->assertStringContainsString('EMAIL DEBUG', $preview);
        $this->assertStringContainsString('Hello', $preview);
        $this->assertStringContainsString('<p>Rendered email</p>', $preview);
        $this->assertStringContainsString('From: from@example.com', $preview);
        $this->assertStringContainsString('To: to@example.com', $preview);

        // consumeDebugPreview() clears the stash, so a second call returns nothing
        $this->assertNull($service->consumeDebugPreview());
    }

    public function testSendStashesOnePreviewPerEmailWhenCopyToEmailSetInDebugMode(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService(
            $mailer,
            ['email-debug' => 'true'],
            isSuperAdmin: true,
            renderedHtml: '<html><body><p>Rendered email</p></body></html>',
        );

        $request = new EmailSendRequest(
            subject: 'Hello',
            context: [],
            template: 'emails/test.html.twig',
            from: 'from@example.com',
            to: 'to@example.com',
            copyToEmail: 'sender@example.com',
        );

        $result = $service->send($request);
        $this->assertTrue($result);
        $this->assertCount(0, $mailer->sent);

        $preview = $service->consumeDebugPreview();
        $this->assertNotNull($preview);
        // Both the "to" recipient email and the sender's copy must be kept, not just the last one
        $this->assertSame(2, substr_count($preview, 'EMAIL DEBUG'));
        $this->assertStringContainsString('To: to@example.com', $preview);
        $this->assertStringContainsString('To: sender@example.com', $preview);

        $this->assertNull($service->consumeDebugPreview());
    }

    public function testSendStillSendsEmailWhenDebugModeEnabledButUserIsNotSuperAdmin(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService(
            $mailer,
            ['email-debug' => 'true'],
            isSuperAdmin: false,
            renderedHtml: '<p>Rendered email</p>',
        );

        $request = new EmailSendRequest(subject: 'Hello', context: [], template: 'emails/test.html.twig', from: 'from@example.com', to: 'to@example.com');
        $result = $service->send($request);

        $this->assertTrue($result);
        $this->assertCount(1, $mailer->sent);
    }

    public function testSendReturnsFalseAndRecordsErrorWhenFromOrToIsMissing(): void
    {
        $service = $this->createService($this->createRecordingMailer());

        $result = $service->send(new EmailSendRequest(subject: 'Hello', context: [], template: 'emails/test.html.twig'));

        $this->assertFalse($result);
        $this->assertSame('Missing email parameter(s)', $service->getLastError());
    }

    // "html" (e.g. EmailTemplateRenderer output) is an alternative to "template" - see EmailSendRequest
    public function testSendUsesRawHtmlBodyWhenHtmlGivenInsteadOfTemplate(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $request = new EmailSendRequest(
            subject: 'Hello',
            context: [],
            html: '<p>Already rendered</p>',
            from: 'from@example.com',
            to: 'to@example.com',
        );

        $result = $service->send($request);

        $this->assertTrue($result);
        $this->assertSame('<p>Already rendered</p>', $mailer->sent[0]->getHtmlBody());
    }

    public function testSendReturnsFalseWhenNoBodyAtAllIsGiven(): void
    {
        $service = $this->createService($this->createRecordingMailer());

        $result = $service->send(new EmailSendRequest(subject: 'Hello', context: [], from: 'from@example.com', to: 'to@example.com'));

        $this->assertFalse($result);
        $this->assertSame('EmailSendRequest needs exactly one of "template", "html" or "text"', $service->getLastError());
    }

    // Two bodies used to go out as whichever buildEmails() tested first, the text one here, dropping the template without a word
    public function testSendReturnsFalseWhenMoreThanOneBodyIsGiven(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $request = new EmailSendRequest(
            subject: 'Hello',
            context: [],
            template: 'emails/test.html.twig',
            from: 'from@example.com',
            to: 'to@example.com',
            text: 'Plain body',
        );

        $result = $service->send($request);

        $this->assertFalse($result);
        $this->assertSame('EmailSendRequest needs exactly one of "template", "html" or "text"', $service->getLastError());
        $this->assertCount(0, $mailer->sent);
    }

    // An operational digest written by a command has no markup and wants none - it goes out as plain text, and the layout registry never sees it
    public function testSendDeliversAPlainTextBodyAsIs(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $result = $service->send(new EmailSendRequest(
            subject: 'Backup Report',
            context: [],
            from: 'from@example.com',
            to: 'to@example.com',
            text: "28 run(s): 28 ok\nLast run on 29/07/2026",
        ));

        $this->assertTrue($result);
        $this->assertSame("28 run(s): 28 ok\nLast run on 29/07/2026", $mailer->sent[0]->getTextBody());
        $this->assertNull($mailer->sent[0]->getHtmlBody());
    }

    // Nothing to re-render and no markup either, so the preview shows the text as it will be received rather than an empty page
    public function testSendStashesAPlainTextBodyAsDebugPreview(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer, ['email-debug' => 'true'], isSuperAdmin: true);

        $service->send(new EmailSendRequest(
            subject: 'Backup Report',
            context: [],
            from: 'from@example.com',
            to: 'to@example.com',
            text: 'Everything <ok>',
        ));

        $preview = $service->consumeDebugPreview();

        $this->assertSame([], $mailer->sent);
        $this->assertStringContainsString('<pre style="white-space:pre-wrap;">Everything &lt;ok&gt;</pre>', $preview);
    }

    // With no template there is nothing to re-render, so the debug preview uses the html body directly
    public function testSendStashesRawHtmlAsDebugPreviewWhenHtmlGivenInDebugMode(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer, ['email-debug' => 'true'], isSuperAdmin: true);

        $request = new EmailSendRequest(
            subject: 'Hello',
            context: [],
            html: '<p>Already rendered</p>',
            from: 'from@example.com',
            to: 'to@example.com',
        );

        $result = $service->send($request);

        $this->assertTrue($result);
        $this->assertCount(0, $mailer->sent);
        $this->assertStringContainsString('<p>Already rendered</p>', (string) $service->consumeDebugPreview());
    }

    // A bundle shipping the body of its email alone: the layout comes from whichever EmailLayoutProviderInterface is registered, so the branding stays in one place instead of one {% extends %} per bundle
    public function testWrapLayoutRendersTheTemplateAndWrapsItThroughTheRegistry(): void
    {
        $registry = new EmailLayoutRegistry();
        $registry->addProvider(new class implements \c975L\UiBundle\Contract\EmailLayoutProviderInterface {
            public function wrap(string $bodyHtml): string
            {
                return '<div id="branded">' . $bodyHtml . '</div>';
            }
        });

        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer, renderedHtml: '<p>body</p>', emailLayoutRegistry: $registry);

        $service->send(new EmailSendRequest(
            subject: 'Order',
            context: [],
            template: '@c975LPayment/emails/confirm_order.html.twig',
            from: 'shop@example.test',
            to: 'buyer@example.test',
            wrapLayout: true,
        ));

        $this->assertSame('<div id="branded"><p>body</p></div>', $mailer->sent[0]->getHtmlBody());
        $this->assertNull($mailer->sent[0]->getHtmlTemplate());
    }

    // No provider registered: the body goes out as it is rather than not at all
    public function testWrapLayoutFallsBackOnTheBodyWhenNoLayoutIsRegistered(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer, renderedHtml: '<p>body</p>');

        $service->send(new EmailSendRequest(
            subject: 'Order',
            context: [],
            template: '@c975LPayment/emails/confirm_order.html.twig',
            from: 'shop@example.test',
            to: 'buyer@example.test',
            wrapLayout: true,
        ));

        $this->assertSame('<p>body</p>', $mailer->sent[0]->getHtmlBody());
    }

    // A blind copy, invisible to the recipient - what a shop keeping a record of every order email needs, and what copyToEmail (a second, separate message) is not
    public function testBccIsAddedToTheEmail(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $service->send(new EmailSendRequest(
            subject: 'Order',
            context: [],
            html: '<p>body</p>',
            from: 'shop@example.test',
            to: 'buyer@example.test',
            bcc: 'archive@example.test',
        ));

        $this->assertSame('archive@example.test', $mailer->sent[0]->getBcc()[0]->getAddress());
    }

    // The copy is a clone of the main email, so it would carry its Bcc along and the archive would receive the message twice
    public function testBccIsNotCarriedOverToTheCopy(): void
    {
        $mailer = $this->createRecordingMailer();
        $service = $this->createService($mailer);

        $service->send(new EmailSendRequest(
            subject: 'Order',
            context: [],
            html: '<p>body</p>',
            from: 'shop@example.test',
            to: 'buyer@example.test',
            copyToEmail: 'buyer-copy@example.test',
            bcc: 'archive@example.test',
        ));

        $this->assertCount(2, $mailer->sent);
        $this->assertSame('archive@example.test', $mailer->sent[0]->getBcc()[0]->getAddress());
        $this->assertSame([], $mailer->sent[1]->getBcc());
    }
}
