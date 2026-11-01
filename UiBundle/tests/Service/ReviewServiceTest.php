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
use c975L\UiBundle\Service\CurrentAccountAddress;
use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Service\ReviewNotifier;
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

        $this->service(verified: true, currentAccountAddress: $this->accountAddress(true))->submit($review);

        $this->assertTrue($review->isVerified());
    }

    public function testPublishingAReviewPutsItsScoreIntoTheOwnerAverage(): void
    {
        $review = $this->localReview(ReviewStatus::Published, 4);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->method('getScale')->willReturn(5);
        $ratingService->expects($this->once())->method('record')->with('book', 12, 4, $this->anything());
        $ratingService->expects($this->never())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // A review is always written out of five, the ratings are held on whatever the site set: 4/5 goes into the average of a site on ten as an 8, not as a 4
    public function testTheScoreIsCarriedOverToTheScaleTheSiteHoldsItsRatingsOn(): void
    {
        $review = $this->localReview(ReviewStatus::Published, 4);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->method('getScale')->willReturn(10);
        $ratingService->expects($this->once())->method('record')->with('book', 12, 8, $this->anything());

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // Rounded rather than truncated, and never below one: the lowest score anyone can leave is not a nought
    public function testTheLowestScoreStaysAScoreOnEveryScale(): void
    {
        $review = $this->localReview(ReviewStatus::Published, 1);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->method('getScale')->willReturn(2);
        $ratingService->expects($this->once())->method('record')->with('book', 12, 1, $this->anything());

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // Anything but "published" takes the score back out, so no transition has to be remembered
    public function testRejectingAReviewTakesItsScoreBackOut(): void
    {
        $review = $this->localReview(ReviewStatus::Rejected, 4);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->method('getScale')->willReturn(5);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->once())->method('withdraw')->with('book', 12, $this->anything());

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // Someone who said something without scoring anything weighs on no average
    public function testAReviewCarryingNoRatingChangesNoAverage(): void
    {
        $review = $this->localReview(ReviewStatus::Published, null);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->method('getScale')->willReturn(5);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->once())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // A review about the site itself has no owner to average it onto
    public function testAReviewWithNoOwnerChangesNoAverage(): void
    {
        $review = new Review()->setStatus(ReviewStatus::Published)->setRating(5);

        $ratingService = $this->createMock(RatingService::class);
        $ratingService->method('getScale')->willReturn(5);
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
        $ratingService->method('getScale')->willReturn(5);
        $ratingService->expects($this->never())->method('record');
        $ratingService->expects($this->never())->method('withdraw');

        $this->service(ratingService: $ratingService)->syncRating($review);
    }

    // A review carrying a typed address is born unverified whatever the verifier says about that address: anyone can type a customer's
    public function testATypedAddressIsNeverVerified(): void
    {
        $review = $this->localReview(ReviewStatus::Pending, 5, 'customer@example.org');

        $this->service(verified: true)->submit($review);
        $this->assertFalse($review->isVerified());

        $review = $this->localReview(ReviewStatus::Pending, 5, 'customer@example.org');
        $this->service(verified: true, currentAccountAddress: $this->accountAddress(false))->submit($review);
        $this->assertFalse($review->isVerified());
    }

    // The address of the account signing the review is proven, and the verifier then answers for it
    public function testTheAccountsOwnAddressIsVerifiedWhenTheVerifierSaysSo(): void
    {
        $review = $this->localReview(ReviewStatus::Pending, 5, 'customer@example.org');
        $this->service(verified: true, currentAccountAddress: $this->accountAddress(true))->submit($review);
        $this->assertTrue($review->isVerified());

        $review = $this->localReview(ReviewStatus::Pending, 5, 'customer@example.org');
        $this->service(verified: false, currentAccountAddress: $this->accountAddress(true))->submit($review);
        $this->assertFalse($review->isVerified());
    }

    // Typing a customer's address must not reach that customer's score: an unverified review weighs under a key of its own
    public function testAnUnverifiedReviewCannotOverwriteAnotherPersonsScore(): void
    {
        $service = $this->service();

        $proven = $service->voterFor($this->localReview(ReviewStatus::Published, 4, 'jean@example.org'));
        $typed = $service->voterFor($this->reviewWithId(7, 'jean@example.org'));
        $otherTyped = $service->voterFor($this->reviewWithId(8, 'jean@example.org'));

        $this->assertNotNull($typed);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $typed);
        $this->assertNotSame($proven, $typed);
        $this->assertNotSame($typed, $otherTyped);
    }

    // Two verified reviews by the same person weigh once, the way two clicks from one browser do
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

    // A review nobody is told about waits in a screen nobody has a reason to open - the site is written to once what the visitor wrote is safely stored
    public function testTheSiteIsToldOnceTheSubmissionIsStored(): void
    {
        $notifier = $this->createMock(ReviewNotifier::class);
        $notifier->expects($this->once())->method('notify');

        $this->service(notifier: $notifier)->submit(new Review()->setAuthorName('Jean D.')->setComment('Impeccable'));
    }

    // The ratings of a site as a plain voter => value table, what the assertions below read: how many rows an owner's average is made of is the whole point
    /** @param array<string, int> $votes */
    private function ratings(array &$votes, int $scale = 5): RatingService
    {
        $service = $this->createStub(RatingService::class);
        $service->method('getScale')->willReturn($scale);
        $service->method('hasVote')->willReturnCallback(static function (string $type, int $id, string $voter) use (&$votes): bool {
            return isset($votes[$voter]);
        });
        $service->method('record')->willReturnCallback(static function (string $type, int $id, int $value, string $voter) use (&$votes): void {
            $votes[$voter] = $value;
        });
        $service->method('withdraw')->willReturnCallback(static function (string $type, int $id, string $voter) use (&$votes): void {
            unset($votes[$voter]);
        });

        return $service;
    }

    /** @param Review[] $reviews */
    private function repositoryHolding(array $reviews): ReviewRepository
    {
        return $this->createConfiguredStub(ReviewRepository::class, ['findLocalByOwnerAndAddress' => $reviews]);
    }

    private static function addressKey(string $email): string
    {
        return substr(hash('sha256', $email), 0, 32);
    }

    // The one row a published unverified review had becomes its own
    public function testRekeyingMovesTheScoreFromTheAddressToTheReview(): void
    {
        $review = $this->reviewWithId(7, 'jean@example.org');
        $votes = [self::addressKey('jean@example.org') => 4];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$review]));

        $this->assertSame(1, $service->rekeyLegacyRating($review));

        $this->assertSame([(string) $service->voterFor($review) => 4], $votes);
    }

    // Run twice, as a command run once per site ends up being
    public function testRekeyingTwiceChangesNothingMore(): void
    {
        $review = $this->reviewWithId(7, 'jean@example.org');
        $votes = [self::addressKey('jean@example.org') => 4];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$review]));

        $service->rekeyLegacyRating($review);
        $after = $votes;

        $this->assertSame(0, $service->rekeyLegacyRating($review));
        $this->assertSame($after, $votes);
    }

    public function testADryRunCountsAndWritesNothing(): void
    {
        $review = $this->reviewWithId(7, 'jean@example.org');
        $votes = [self::addressKey('jean@example.org') => 4];
        $before = $votes;
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$review]));

        $this->assertSame(1, $service->rekeyLegacyRating($review, true));
        $this->assertSame($before, $votes);
    }

    // One row stood for both: moving it for the first must not take the second out of the average
    public function testTwoUnverifiedReviewsOfAnAddressEachGetTheirOwn(): void
    {
        $first = $this->reviewWithId(7, 'jean@example.org');
        $second = $this->reviewWithId(8, 'Jean@Example.org')->setRating(2);
        $votes = [self::addressKey('jean@example.org') => 2];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$first, $second]));

        $this->assertSame(2, $service->rekeyLegacyRating($first));

        $this->assertSame([(string) $service->voterFor($first) => 4, (string) $service->voterFor($second) => 2], $votes);
    }

    // The address's row is a verified review's own key: it stays, carrying that review's score again and not the one an unverified review wrote over it
    public function testTheRowOfAVerifiedReviewOfThatAddressStays(): void
    {
        $verified = $this->reviewWithId(6, 'jean@example.org')->setVerified(true)->setRating(5);
        $unverified = $this->reviewWithId(7, 'jean@example.org')->setRating(1);
        $address = self::addressKey('jean@example.org');
        $votes = [$address => 1];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$verified, $unverified]));

        $this->assertSame(1, $service->rekeyLegacyRating($unverified));

        $this->assertSame([$address => 5, (string) $service->voterFor($unverified) => 1], $votes);
    }

    // Rejected before it moved, the unverified review will never carry the score it wrote over the verified one
    public function testRejectingAnUnverifiedReviewGivesTheVerifiedRowItsScoreBack(): void
    {
        $verified = $this->reviewWithId(6, 'jean@example.org')->setVerified(true)->setRating(5);
        $unverified = $this->reviewWithId(7, 'jean@example.org')->setRating(1)->setStatus(ReviewStatus::Rejected);
        $address = self::addressKey('jean@example.org');
        $votes = [$address => 1];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$verified, $unverified]));

        $this->assertSame(0, $service->rekeyLegacyRating($unverified));

        $this->assertSame([$address => 5], $votes);
    }

    // A verified review's row is left alone once done, a moderator having republished another one of that address meanwhile being no business of this
    public function testNothingLeftToMoveLeavesTheVerifiedRowAlone(): void
    {
        $verified = $this->reviewWithId(6, 'jean@example.org')->setVerified(true)->setRating(5);
        $unverified = $this->reviewWithId(7, 'jean@example.org')->setRating(1);
        $address = self::addressKey('jean@example.org');
        $votes = [$address => 3];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$verified, $unverified]));
        $votes[(string) $service->voterFor($unverified)] = 1;

        $this->assertSame(0, $service->rekeyLegacyRating($unverified));

        $this->assertSame(3, $votes[$address]);
    }

    // On the site's own scale, as the score it replaces was
    public function testRekeyingWritesOnTheSiteScale(): void
    {
        $review = $this->reviewWithId(7, 'jean@example.org');
        $votes = [self::addressKey('jean@example.org') => 8];
        $service = $this->service(ratingService: $this->ratings($votes, 10), reviewRepository: $this->repositoryHolding([$review]));

        $service->rekeyLegacyRating($review);

        $this->assertSame([(string) $service->voterFor($review) => 8], $votes);
    }

    // A site moderating before it ran the command: the score stands once, not under both keys
    public function testPublishingAgainWithoutTheCommandDoesNotCountTwice(): void
    {
        $review = $this->reviewWithId(7, 'jean@example.org');
        $votes = [self::addressKey('jean@example.org') => 4];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$review]));

        $service->syncRating($review);

        $this->assertSame([(string) $service->voterFor($review) => 4], $votes);
    }

    // Rejected before the command ran: its score goes, and the other review the row also stood for keeps its own
    public function testRejectingWithoutTheCommandLeavesTheOtherReviewCounted(): void
    {
        $rejected = $this->reviewWithId(7, 'jean@example.org')->setStatus(ReviewStatus::Rejected);
        $other = $this->reviewWithId(8, 'jean@example.org')->setRating(2);
        $votes = [self::addressKey('jean@example.org') => 4];
        $service = $this->service(ratingService: $this->ratings($votes), reviewRepository: $this->repositoryHolding([$rejected, $other]));

        $service->syncRating($rejected);

        $this->assertSame([(string) $service->voterFor($other) => 2], $votes);
    }

    // A review imported from a platform, or carrying no address, was never under one
    public function testAReviewWithNoAddressHasNothingToMove(): void
    {
        $votes = [];
        $service = $this->service(ratingService: $this->ratings($votes));

        $this->assertSame(0, $service->rekeyLegacyRating(new Review()->setOwnerType('book')->setOwnerId(12)->setVerified(false)));
    }

    private function accountAddress(bool $owns): CurrentAccountAddress
    {
        $address = $this->createStub(CurrentAccountAddress::class);
        $address->method('owns')->willReturn($owns);

        return $address;
    }

    // An unverified review as moderation sees it: stored, hence its id, set the way Doctrine does
    private function reviewWithId(int $id, string $email): Review
    {
        $review = $this->localReview(ReviewStatus::Published, 4, $email)->setVerified(false);
        new \ReflectionProperty(Review::class, 'id')->setValue($review, $id);

        return $review;
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
        ?ReviewNotifier $notifier = null,
        ?CurrentAccountAddress $currentAccountAddress = null,
        ?ReviewRepository $reviewRepository = null,
    ): ReviewService {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(fn (string $key): bool => \array_key_exists($key, $configs));
        $configService->method('get')->willReturnCallback(fn (string $key): mixed => $configs[$key] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn ($value): bool => '1' === $value);

        $verifierRegistry = $this->createStub(ReviewVerifierRegistry::class);
        $verifierRegistry->method('verify')->willReturn($verified);

        return new ReviewService(
            $reviewRepository ?? $this->createMock(ReviewRepository::class),
            $ratingService ?? $this->createMock(RatingService::class),
            $replyRegistry ?? $this->createMock(ReviewReplyRegistry::class),
            $verifierRegistry,
            $manager ?? $this->createMock(EntityManagerInterface::class),
            $configService,
            $notifier ?? $this->createMock(ReviewNotifier::class),
            $currentAccountAddress,
        );
    }
}
