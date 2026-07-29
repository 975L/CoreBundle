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

        return new EmailService($configService, $mailer, $twig, $security);
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

    public function testSendReturnsFalseWhenNeitherTemplateNorHtmlGiven(): void
    {
        $service = $this->createService($this->createRecordingMailer());

        $result = $service->send(new EmailSendRequest(subject: 'Hello', context: [], from: 'from@example.com', to: 'to@example.com'));

        $this->assertFalse($result);
        $this->assertSame('EmailSendRequest needs either "template" or "html"', $service->getLastError());
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
}
