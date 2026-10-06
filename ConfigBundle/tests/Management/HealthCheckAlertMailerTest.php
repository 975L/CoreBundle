<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAlertMailer;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HealthCheckAlertMailerTest extends TestCase
{
    private array $sent = [];

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    // The very case that went unnoticed for weeks: files fine yesterday, missing today
    public function testAnErrorThatWasNotOneBeforeIsMailed(): void
    {
        $mailer = $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR, 'Gallery')]);

        $this->assertSame(1, $mailer->notify(['files-gallery']));
        $this->assertCount(1, $this->sent);
        $this->assertSame('ops@example.com', $this->sent[0]->to);
        $this->assertStringContainsString('[files-gallery] Gallery: 12 missing file(s)', $this->sent[0]->text);
        $this->assertStringContainsString('https://example.com/management/health-check', $this->sent[0]->text);
    }

    // A failure already known is not mailed again at every run
    public function testAnErrorAlreadyKnownIsNotMailedAgain(): void
    {
        $mailer = $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR)]);

        $this->assertSame(1, $mailer->notify(['files-gallery']));
        $this->assertSame(0, $mailer->notify(['files-gallery']));
        $this->assertCount(1, $this->sent);
    }

    // The very reason the mailed keys are kept apart from the results: a mail that did not leave is sent at the next run
    public function testAnErrorWhoseMailFailedIsMailedAtTheNextRun(): void
    {
        $results = [$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR)];

        $this->assertNull($this->createMailer($results, sends: false)->notify(['files-gallery']));
        $this->assertSame(1, $this->createMailer($results)->notify(['files-gallery']));
        $this->assertCount(2, $this->sent);
    }

    // Fixed then broken again: a new failure, mailed again
    public function testAnErrorBackToOkIsForgotten(): void
    {
        $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR)])->notify(['files-gallery']);
        $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_OK)])->notify(['files-gallery']);

        $this->assertSame(1, $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR)])->notify(['files-gallery']));
        $this->assertCount(2, $this->sent);
    }

    // A run narrowed to one kind says nothing about the others, whose latest rows are older than it
    public function testAnErrorOfAKindThatDidNotRunIsLeftAlone(): void
    {
        $mailer = $this->createMailer([$this->createResult('pagespeed', '/', HealthCheckResult::STATUS_ERROR)]);

        $this->assertSame(0, $mailer->notify(['files-gallery']));
        $this->assertSame([], $this->sent);
    }

    public function testNothingIsSentWithoutRecipient(): void
    {
        $mailer = $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR)], '');

        $this->assertSame(0, $mailer->notify(['files-gallery']));
        $this->assertSame([], $this->sent);
    }

    public function testAFailedSendReturnsNull(): void
    {
        $mailer = $this->createMailer([$this->createResult('files-gallery', '/gallery', HealthCheckResult::STATUS_ERROR)], sends: false);

        $this->assertNull($mailer->notify(['files-gallery']));
    }

    private function createResult(string $kind, string $url, string $status, ?string $label = null): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setKind($kind)
            ->setUrl($url)
            ->setLabel($label)
            ->setStatus($status)
            ->setSummary('12 missing file(s)')
            ->setCheckedAt(new \DateTime('2026-10-06 04:00:00'));
    }

    private function createMailer(array $results, string $mailto = 'ops@example.com', bool $sends = true): HealthCheckAlertMailer
    {
        $repository = $this->createStub(HealthCheckResultRepository::class);
        $repository->method('findLatestPerUrlAndKind')->willReturn($results);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $name): string => match ($name) {
            'site-backup-mailto' => $mailto,
            'site-name' => 'Example',
            default => '',
        });

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use ($sends): bool {
            $this->sent[] = $request;

            return $sends;
        });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/management/health-check');

        return new HealthCheckAlertMailer($repository, $configService, $emailService, $urlGenerator, $this->cache);
    }
}
