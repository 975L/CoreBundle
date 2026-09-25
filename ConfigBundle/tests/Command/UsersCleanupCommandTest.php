<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\UsersCleanupCommand;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\InactiveUserFinder;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersCleanupCommandTest extends TestCase
{
    // 0 days keeps every account, without even looking for them
    public function testDoesNothingWhenDisabled(): void
    {
        $finder = $this->createMock(InactiveUserFinder::class);
        $finder->expects($this->never())->method('findToNotify');

        $tester = new CommandTester($this->command(['user-inactivity-days' => 0], $finder));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('inactive accounts are kept', $tester->getDisplay());
    }

    // A site whose User doesn't track its inactivity yet is left alone
    public function testDoesNothingWhenTheUserDoesNotTrackItsInactivity(): void
    {
        $finder = $this->createMock(InactiveUserFinder::class);
        $finder->method('isSupported')->willReturn(false);
        $finder->expects($this->never())->method('findToAnonymize');

        $tester = new CommandTester($this->command([], $finder));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('does not implement InactivityAwareInterface', $tester->getDisplay());
    }

    // A notice as long as the inactivity would warn every account, so the run stops before touching any
    public function testFailsWhenTheNoticeIsNotShorterThanTheInactivity(): void
    {
        $finder = $this->createMock(InactiveUserFinder::class);
        $finder->method('isSupported')->willReturn(true);
        $finder->expects($this->never())->method('startMissingClocks');
        $finder->expects($this->never())->method('findToNotify');
        $finder->expects($this->never())->method('findToAnonymize');

        $tester = new CommandTester($this->command(['user-inactivity-days' => 30, 'user-inactivity-notice-days' => 30], $finder));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('must be lower than', $tester->getDisplay());
    }

    // The accounts created before the clock existed get theirs started, rather than being skipped forever
    public function testStartsTheMissingClocks(): void
    {
        $finder = $this->createMock(InactiveUserFinder::class);
        $finder->method('isSupported')->willReturn(true);
        $finder->method('findToNotify')->willReturn([]);
        $finder->method('findToAnonymize')->willReturn([]);
        $finder->expects($this->once())->method('startMissingClocks');

        new CommandTester($this->command([], $finder))->execute([]);
    }

    // The limits are counted from the configuration: warned at the limit minus the notice, anonymized at the limit once warned for the whole notice
    public function testQueriesTheLimitsFromTheConfiguration(): void
    {
        $finder = $this->createMock(InactiveUserFinder::class);
        $finder->method('isSupported')->willReturn(true);
        $finder->expects($this->once())->method('findToAnonymize')->with(
            $this->callback(fn (\DateTimeInterface $date): bool => $this->isDaysAgo($date, 100)),
            $this->callback(fn (\DateTimeInterface $date): bool => $this->isDaysAgo($date, 10)),
        )->willReturn([]);
        $finder->expects($this->once())->method('findToNotify')->with(
            $this->callback(fn (\DateTimeInterface $date): bool => $this->isDaysAgo($date, 90)),
        )->willReturn([]);

        new CommandTester($this->command(['user-inactivity-days' => 100, 'user-inactivity-notice-days' => 10], $finder))->execute([]);
    }

    // An account past its notice is anonymized, the app being told so it can unlink what the account owned
    public function testAnonymizesAndDispatches(): void
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->expects($this->once())->method('anonymize');

        $finder = $this->finder(toAnonymize: [$user]);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->with($this->callback(fn (object $event): bool => $event instanceof UserAnonymizedEvent && $event->user === $user));

        $tester = new CommandTester($this->command([], $finder, $dispatcher));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('0 account(s) warned, 1 anonymized', $tester->getDisplay());
    }

    // The notice date is only written once the email has left, so an email that failed is tried again next run
    public function testWritesTheNoticeDateOnlyWhenTheEmailLeft(): void
    {
        $sent = $this->user('sent@example.com');
        $sent->expects($this->once())->method('setInactivityNoticeSentAt')->with($this->isInstanceOf(\DateTimeInterface::class));
        $failed = $this->user('failed@example.com');
        $failed->expects($this->never())->method('setInactivityNoticeSentAt');

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(fn (EmailSendRequest $request): bool => 'sent@example.com' === $request->to);

        $tester = new CommandTester($this->command([], $this->finder(toNotify: [$sent, $failed]), emailService: $emailService));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('1 account(s) warned, 0 anonymized', $tester->getDisplay());
    }

    // A template deleted from the back-office sends nothing rather than an empty email
    public function testSendsNothingWithoutTheTemplate(): void
    {
        $user = $this->user('someone@example.com');
        $user->expects($this->never())->method('setInactivityNoticeSentAt');
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        new CommandTester($this->command([], $this->finder(toNotify: [$user]), emailService: $emailService, html: null))->execute([]);
    }

    /** @param array<string, int> $configs */
    private function command(array $configs, InactiveUserFinder $finder, ?EventDispatcherInterface $dispatcher = null, ?EmailService $emailService = null, ?string $html = '<p>notice</p>'): UsersCleanupCommand
    {
        $configs += ['user-inactivity-days' => 1095, 'user-inactivity-notice-days' => 30, 'site-url' => 'https://example.com'];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(fn (string $slug): mixed => $configs[$slug] ?? null);

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturn($html);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new UsersCleanupCommand(
            $configService,
            $emailService ?? $this->createStub(EmailService::class),
            $renderer,
            $this->createStub(EntityManagerInterface::class),
            $dispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $finder,
            $translator,
            $urlGenerator,
            'fr',
        );
    }

    /**
     * @param list<InactivityAwareInterface> $toNotify
     * @param list<InactivityAwareInterface> $toAnonymize
     */
    private function finder(array $toNotify = [], array $toAnonymize = []): InactiveUserFinder
    {
        $finder = $this->createStub(InactiveUserFinder::class);
        $finder->method('isSupported')->willReturn(true);
        $finder->method('findToNotify')->willReturn($toNotify);
        $finder->method('findToAnonymize')->willReturn($toAnonymize);

        return $finder;
    }

    private function user(string $email): InactivityAwareInterface & \PHPUnit\Framework\MockObject\MockObject
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    private function isDaysAgo(\DateTimeInterface $date, int $days): bool
    {
        return abs($date->getTimestamp() - new \DateTime('-' . $days . ' days')->getTimestamp()) < 60;
    }
}
