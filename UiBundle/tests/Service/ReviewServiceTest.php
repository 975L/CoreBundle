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
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Registry\ReviewReplyRegistry;
use c975L\UiBundle\Registry\ReviewVerifierRegistry;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// What a review decides between being written and being read: what a submission is born as, what publishing it does to the owner's average, and where an answer goes
#[AllowMockObjectsWithoutExpectations]
class ReviewServiceTest extends TestCase
{
    // The two fields deciding whether a text is readable and whether the site stands behind it are never the author's to fill: a forged post setting them is overwritten rather than refused
    public function testASubmissionIsBornPendingUnverifiedAndLocalWhateverTheFormSent(): void
    {
        $review = new Review()
            ->setStatus(ReviewStatus::Published)
            ->setVerified(true)
            ->setSource('google')
            ->setExternalId('forged')
        ;

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($review);
        $manager->expects($this->once())->method('flush');

        $this->service(manager: $manager)->submit($review);

        $this->assertSame(ReviewStatus::Pending, $review->getStatus());
        $this->assertFalse($review->isVerified());
        $this->assertSame(Review::SOURCE_SITE, $review->getSource());
        $this->assertNull($review->getExternalId());
    }

    // A site bringing no verifier vouches for nothing, which is what keeps the badge honest: it says the site checked, not that it had no way to
    public function testASubmissionStaysUnverifiedWhenNobodyVouchesForIt(): void
    {
        $review = $this->localReview(ReviewStatus::Pending, 4);

        $this->service()->submit($review);

        $this->assertFalse($review->isVerified());
    }

    // Settled once at submission and never asked again: an order archived years later must not un-verify a review nobody can re-examine
    public function testASubmissionIsVerifiedWhenAVerifierVouchesForIt(): void
    {
        $review = $this->localReview(ReviewStatus::Pending, 4);

        $this->service(verified: true)->submit($review);

        $this->assertTrue($review->isVerified());
    }

    public function testPublishingAReviewPutsItsScoreIntoTheOwnerAverage(): void
    {
        $review = $this->localReview(ReviewStatus::Published, 4);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->expects($this->once())->method('record')->with('book', 12, 4, $this->anything());
        $ratingService->expects($this->never())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // Anything but "published" takes the score back out, so no transition has to be remembered
    public function testRejectingAReviewTakesItsScoreBackOut(): void
    {
        $review = $this->localReview(ReviewStatus::Rejected, 4);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->once())->method('withdraw')->with('book', 12, $this->anything());

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // Someone who said something without scoring anything weighs on no average
    public function testAReviewCarryingNoRatingChangesNoAverage(): void
    {
        $review = $this->localReview(ReviewStatus::Published, null);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->once())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // A review about the site itself has no owner to average it onto
    public function testAReviewWithNoOwnerChangesNoAverage(): void
    {
        $review = new Review()->setStatus(ReviewStatus::Published)->setRating(5);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->never())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // An imported review carries no address of its author, and its platform counted it on its own side already
    public function testAnImportedReviewIsNeverAveragedIntoTheOwnerScore(): void
    {
        $review = new Review()
            ->setSource('google')
            ->setExternalId('r1')
            ->setOwnerType('book')
            ->setOwnerId(12)
            ->setStatus(ReviewStatus::Published)
            ->setRating(5)
        ;

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->never())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // Two reviews by the same person weigh once, the way two clicks from one browser do
    public function testTheVoterKeyIsTheSameForOneAddressAndDiffersForAnother(): void
    {
        $service = $this->service();

        $first = $service->voterFor($this->localReview(ReviewStatus::Published, 4, 'Jean@Example.org'));
        $second = $service->voterFor($this->localReview(ReviewStatus::Published, 2, '  jean@example.org  '));
        $other = $service->voterFor($this->localReview(ReviewStatus::Published, 4, 'paul@example.org'));

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $other);
    }

    // Rating::$voter is 40 characters and RatingService::resolveVoter() only accepts 32 hexadecimal ones - a key of any other shape would be stored but never matched by a click
    public function testTheVoterKeyLooksExactlyLikeABrowserToken(): void
    {
        $voter = $this->service()->voterFor($this->localReview(ReviewStatus::Published, 4));

        $this->assertNotNull($voter);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $voter);
    }

    // The address itself is never stored anywhere near the average: Rating holds no personal data of anyone
    public function testTheVoterKeyIsNotTheAddress(): void
    {
        $voter = $this->service()->voterFor($this->localReview(ReviewStatus::Published, 4, 'jean@example.org'));

        $this->assertNotNull($voter);
        $this->assertStringNotContainsString('jean', $voter);
    }

    // The platform goes first: a reply saved here but refused there would show the visitor an answer its author never received
    public function testTheReplyIsPublishedBeforeItIsStored(): void
    {
        $review = $this->localReview(ReviewStatus::Published, 4);

        $registry = $this->createMock(ReviewReplyRegistry::class);
        $registry->expects($this->once())->method('publish')->with($review);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('flush');

        $this->service(manager: $manager, replyRegistry: $registry)->reply($review, '  Merci !  ');

        $this->assertSame('Merci !', $review->getReplyComment());
        $this->assertNotNull($review->getRepliedAt());
    }

    // An emptied textarea means "remove the reply", which only null says on a platform's side
    public function testAnEmptiedReplyIsStoredAsNullAndDatedNowhere(): void
    {
        $review = $this->localReview(ReviewStatus::Published, 4)->setReplyComment('Merci !')->setRepliedAt(new \DateTimeImmutable());

        $this->service()->reply($review, '   ');

        $this->assertNull($review->getReplyComment());
        $this->assertNull($review->getRepliedAt());
    }

    public function testTheFeatureIsOffUntilTheSiteTurnsItOn(): void
    {
        $this->assertFalse($this->service()->isEnabled());
        $this->assertTrue($this->service(configs: ['ui-enable-reviews' => '1'])->isEnabled());
    }

    private function localReview(ReviewStatus $status, ?int $rating, string $email = 'jean@example.org'): Review
    {
        return new Review()
            ->setOwnerType('book')
            ->setOwnerId(12)
            ->setStatus($status)
            ->setRating($rating)
            ->setAuthorEmail($email)
        ;
    }

    /**
     * @param array<string, string> $configs
     */
    private function service(
        ?EntityManagerInterface $manager = null,
        ?RatingService $ratingService = null,
        ?ReviewReplyRegistry $replyRegistry = null,
        array $configs = [],
        bool $verified = false,
    ): ReviewService {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(fn (string $key): bool => \array_key_exists($key, $configs));
        $configService->method('get')->willReturnCallback(fn (string $key): mixed => $configs[$key] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn ($value): bool => '1' === $value);

        $verifierRegistry = $this->createStub(ReviewVerifierRegistry::class);
        $verifierRegistry->method('verify')->willReturn($verified);

        return new ReviewService(
            $this->createMock(ReviewRepository::class),
            $ratingService ?? $this->createMock(RatingService::class),
            $replyRegistry ?? $this->createMock(ReviewReplyRegistry::class),
            $verifierRegistry,
            $manager ?? $this->createMock(EntityManagerInterface::class),
            $configService,
        );
    }
}
