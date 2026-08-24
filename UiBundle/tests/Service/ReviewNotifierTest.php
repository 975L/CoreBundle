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
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\ReviewNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviewNotifierTest extends TestCase
{
    /** @param array<string, mixed> $values */
    private function createConfigService(array $values = []): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): mixed => $values[$slug] ?? null);

        return $configService;
    }

    // The site's own locale is passed explicitly, so the notice doesn't come out in whatever language the visitor was browsing in
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters, string $domain, ?string $locale): string => $id . '|' . $locale . '|' . implode(',', $parameters)
        );

        return $translator;
    }

    private function createReview(?string $comment = 'A few words', ?int $rating = 4): Review
    {
        return new Review()
            ->setAuthorName('Jeanne')
            ->setComment($comment)
            ->setRating($rating)
        ;
    }

    // No "to" is set on the request: EmailService falls back on the site-wide "email-to" address, the very one every other c975L email already goes to
    public function testNotifySendsThePlainTextNoticeToTheSiteAddress(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => 'My Site - label.review_submitted_notification|fr|' === $request->subject
                && 'text.review_submitted_notification|fr|Jeanne,4/5,A few words' === $request->text
                && null === $request->to
                && null === $request->template
                && null === $request->html))
            ->willReturn(true);

        $notifier = new ReviewNotifier(
            $emailService,
            $this->createConfigService(['site-name' => 'My Site']),
            $this->createTranslator(),
            'fr'
        );

        $this->assertTrue($notifier->notify($this->createReview()));
    }

    // A site that hasn't set its name gets the plain label, not a subject opening on a dangling separator
    public function testNotifyFallsBackOnTheBareLabelWithoutASiteName(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => 'label.review_submitted_notification|en|' === $request->subject))
            ->willReturn(true);

        $notifier = new ReviewNotifier($emailService, $this->createConfigService(), $this->createTranslator(), 'en');

        $notifier->notify($this->createReview());
    }

    // Someone said something without scoring anything, which the notice says rather than printing a nought out of five
    public function testAReviewWithNoRatingIsNotifiedWithADash(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => str_contains((string) $request->text, 'Jeanne,-,')))
            ->willReturn(true);

        $notifier = new ReviewNotifier($emailService, $this->createConfigService(), $this->createTranslator(), 'fr');

        $notifier->notify($this->createReview(rating: null));
    }

    // Enough of the text to know whether it needs answering today, the whole of it being one click away
    public function testALongCommentTravelsAsAnExcerpt(): void
    {
        $comment = str_repeat('a', 350);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => str_contains((string) $request->text, str_repeat('a', 300) . '...')
                && !str_contains((string) $request->text, str_repeat('a', 301))))
            ->willReturn(true);

        $notifier = new ReviewNotifier($emailService, $this->createConfigService(), $this->createTranslator(), 'fr');

        $notifier->notify($this->createReview(comment: $comment));
    }

    // A comment of exactly the excerpt length is not worth an ellipsis promising something more
    public function testACommentAtTheLimitTravelsWhole(): void
    {
        $comment = str_repeat('b', 300);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (EmailSendRequest $request): bool => str_ends_with((string) $request->text, $comment)))
            ->willReturn(true);

        $notifier = new ReviewNotifier($emailService, $this->createConfigService(), $this->createTranslator(), 'fr');

        $notifier->notify($this->createReview(comment: $comment));
    }

    // A mailer failure is reported, never thrown: what the visitor wrote is stored whatever happens to this email
    public function testNotifyReturnsFalseWhenTheEmailCouldNotBeSent(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);

        $notifier = new ReviewNotifier($emailService, $this->createConfigService(), $this->createTranslator(), 'fr');

        $this->assertFalse($notifier->notify($this->createReview()));
    }
}
