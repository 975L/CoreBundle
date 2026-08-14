<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UserCreationNotifier;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCreationNotifierTest extends TestCase
{
    /** @param array<string, mixed> $values */
    private function createConfigService(array $values): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): mixed => $values[$slug] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn ($value): bool => filter_var($value, \FILTER_VALIDATE_BOOLEAN));

        return $configService;
    }

    // The site's own locale is passed explicitly, so the notification doesn't come out in whatever language the visitor was browsing in
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters, string $domain, ?string $locale): string => $id . '|' . $locale . '|' . implode(',', $parameters)
        );

        return $translator;
    }

    // No "to" is set on the request: EmailService falls back on the site-wide "email-to" address, the very one every other c975L email already goes to
    public function testNotifySendsToTheSiteAddressWhenTheConfigIsOn(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => 'My Site - label.user_created|fr|' === $request->subject
                && 'text.user_created|fr|new-user@example.test' === $request->text
                && null === $request->to
                && null === $request->template
                && null === $request->html))
            ->willReturn(true);

        $notifier = new UserCreationNotifier(
            $this->createConfigService(['user-creation-notification' => 'true', 'site-name' => 'My Site']),
            $emailService,
            $this->createTranslator(),
            'fr'
        );

        $this->assertTrue($notifier->notify(new UserStub('new-user@example.test')));
    }

    // A site that hasn't set its name gets the plain label, not a subject opening on a dangling separator
    public function testNotifyFallsBackOnTheBareLabelWithoutASiteName(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => 'label.user_created|en|' === $request->subject))
            ->willReturn(true);

        $notifier = new UserCreationNotifier(
            $this->createConfigService(['user-creation-notification' => 'true']),
            $emailService,
            $this->createTranslator(),
            'en'
        );

        $notifier->notify(new UserStub('new-user@example.test'));
    }

    public function testNotifySendsNothingWhenTheConfigIsOff(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $notifier = new UserCreationNotifier(
            $this->createConfigService(['user-creation-notification' => 'false']),
            $emailService,
            $this->createTranslator(),
            'fr'
        );

        $this->assertFalse($notifier->notify(new UserStub('new-user@example.test')));
    }

    // A mailer failure is reported, never thrown: the caller registers a user whatever happens to this email
    public function testNotifyReturnsFalseWhenTheEmailCouldNotBeSent(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);

        $notifier = new UserCreationNotifier(
            $this->createConfigService(['user-creation-notification' => 'true']),
            $emailService,
            $this->createTranslator(),
            'fr'
        );

        $this->assertFalse($notifier->notify(new UserStub('new-user@example.test')));
    }
}
