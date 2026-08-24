<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Registry\ReviewReplyRegistry;
use c975L\UiBundle\Registry\ReviewVerifierRegistry;
use c975L\UiBundle\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

// Everything a review decides between being written and being read: what a submission is born as, what publishing it does to the owner's average, and where an answer goes. Kept out of the controllers so that the public form and the moderation screen reach the same rules
class ReviewService
{
    // Longest text a visitor may submit, the column being a TEXT and the limit therefore ours to set. Wide enough for anything anyone actually writes about a book, narrow enough that a paste of a whole novel is refused before it reaches the database
    public const int MAX_COMMENT_LENGTH = 4000;

    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly RatingService $ratingService,
        private readonly ReviewReplyRegistry $reviewReplyRegistry,
        private readonly ReviewVerifierRegistry $reviewVerifierRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly ReviewNotifier $reviewNotifier,
    ) {
    }

    // Whether the site collects and displays reviews at all - read at every entry point rather than at one, a feature turned off having to be off on the public form as much as in the menu
    public function isEnabled(): bool
    {
        return $this->configService->hasParameter('ui-enable-reviews')
            && $this->configService->getBool($this->configService->get('ui-enable-reviews'));
    }

    /**
     * Stores what a visitor just wrote, and stores nothing else.
     *
     * A submission is born pending whatever the form sent, and vouched for by nobody but a verifier: the two fields deciding if a text is readable and if the site stands behind it are never the author's to fill, and a forged post setting them is answered by overwriting them rather than by an error nobody reads.
     *
     * Whether it is verified is settled here, once, and never asked again: it is a snapshot of what was true the day the review was written, like the lines of the order it may be checked against. An order archived or purged years later must not un-verify a review nobody can re-examine.
     */
    public function submit(Review $review): void
    {
        $review
            ->setSource(Review::SOURCE_SITE)
            ->setExternalId(null)
            ->setStatus(ReviewStatus::Pending)
            ->setVerified($this->reviewVerifierRegistry->verify($review))
            ->setPublishedAt(new \DateTimeImmutable())
        ;

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        // After the flush and its result ignored: what the visitor wrote is stored whatever the mailer answers, and a site with no "email-to" seeded still collects reviews
        $this->reviewNotifier->notify($review);
    }

    /**
     * Brings the owner's average in line with what the review currently is.
     *
     * Called after every change of status rather than on the transition, so it needs no memory of what the review was before: a published review carrying a score puts it in, anything else takes it back out. Publishing twice, or rejecting something that was never published, is then a no-op rather than a wrong average.
     *
     * A review about the site itself changes no average at all - there is no owner to average it onto.
     */
    public function syncRating(Review $review): void
    {
        if (!$review->hasOwner()) {
            return;
        }

        $ownerType = (string) $review->getOwnerType();
        $ownerId = (int) $review->getOwnerId();
        $voter = $this->voterFor($review);

        if (null === $voter) {
            return;
        }

        if (ReviewStatus::Published === $review->getStatus() && null !== $review->getRating()) {
            $this->ratingService->record($ownerType, $ownerId, $this->onSiteScale($review->getRating()), $voter);

            return;
        }

        $this->ratingService->withdraw($ownerType, $ownerId, $voter);
    }

    /**
     * Stores an answer and carries it to the platform the review came from, when there is one.
     *
     * The platform goes first: a reply saved here but refused there would show the visitor an answer its author never received. A review written on this site has no platform, and the same call simply stores it.
     */
    public function reply(Review $review, ?string $comment): void
    {
        $comment = self::normalizeComment($comment);

        $review
            ->setReplyComment($comment)
            ->setRepliedAt(null === $comment ? null : new \DateTimeImmutable())
        ;

        $this->reviewReplyRegistry->publish($review);
        $this->entityManager->flush();
    }

    /**
     * The score as the ratings hold it, a review writing it on a scale of its own.
     *
     * A review is always out of five (Review::SCALE), so a local one and one imported from a platform are read on the same scale; the ratings are held on whatever the site set in "ui-rating-scale". Left as it is, a 5/5 would go into the average as a 5/10 on a site that set ten.
     *
     * Rounded rather than truncated, and never below one: a 1/5 is a 2/10, not a nought.
     */
    private function onSiteScale(int $rating): int
    {
        $scale = $this->ratingService->getScale();

        return Review::SCALE === $scale ? $rating : max(1, (int) round($rating * $scale / Review::SCALE));
    }

    // An emptied textarea arrives as "" and means "remove the reply", which only null says on a platform's side. Public and static so the moderation screen compares what it is about to store against what is stored, rather than "" against null on every save
    public static function normalizeComment(?string $comment): ?string
    {
        $comment = null === $comment ? null : trim($comment);

        return '' === $comment ? null : $comment;
    }

    // Whether an answer can be written on this review, which the moderation screen asks before offering the field
    public function canReply(Review $review): bool
    {
        return $this->reviewReplyRegistry->supports($review);
    }

    // How many reviews are waiting for a decision, for the badge the management menu shows
    public function countPending(): int
    {
        return $this->reviewRepository->countPending();
    }

    /**
     * The key a review's score is stored under in Rating, so that two reviews by the same person weigh once.
     *
     * A hash rather than the address itself: Rating::$voter holds no personal data of anyone (see RatingService::resolveVoter(), where an anonymous voter is a token their browser made up), and the average has no use for who wrote what. Truncated to the 32 hexadecimal characters that column accepts, which is also what the token pattern looks like - a review's score is then indistinguishable from a click, which is exactly what it is.
     *
     * Null when the review carries no address, an imported review having none: its score stays on the review and is never averaged into the owner's, the platform having counted it already on its own side.
     */
    public function voterFor(Review $review): ?string
    {
        $email = $review->getAuthorEmail();

        if (!$review->isLocal() || null === $email || '' === trim($email)) {
            return null;
        }

        return substr(hash('sha256', strtolower(trim($email))), 0, 32);
    }
}
