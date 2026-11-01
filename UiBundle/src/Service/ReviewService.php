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
        // Optional so an app wiring this by hand keeps working - without it, no address is proven and no review is verified
        private readonly ?CurrentAccountAddress $currentAccountAddress = null,
    ) {
    }

    // Whether the site collects and displays reviews at all - read at every entry point rather than at one, a feature turned off having to be off on the public form as much as in the menu
    public function isEnabled(): bool
    {
        return $this->configService->hasParameter('ui-enable-reviews')
            && $this->configService->getBool($this->configService->get('ui-enable-reviews'));
    }

    // Stores what a visitor just wrote, born pending and vouched for by nobody but a verifier whatever the form sent. Verified is a snapshot settled here once, an order archived later must not un-verify a review, and only for the address of the account signing it, anyone being able to type a customer's
    public function submit(Review $review): void
    {
        $review
            ->setSource(Review::SOURCE_SITE)
            ->setExternalId(null)
            ->setStatus(ReviewStatus::Pending)
            ->setVerified(true === $this->currentAccountAddress?->owns($review->getAuthorEmail()) && $this->reviewVerifierRegistry->verify($review))
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

        // Ahead of what follows, so a site that has not run the command yet does not count a score under both keys
        $this->rekeyLegacyRating($review);

        if (ReviewStatus::Published === $review->getStatus() && null !== $review->getRating()) {
            $this->ratingService->record($ownerType, $ownerId, $this->onSiteScale($review->getRating()), $voter);

            return;
        }

        $this->ratingService->withdraw($ownerType, $ownerId, $voter);
    }

    // Moves the scores an address held together, before an unverified review weighed under its own key, onto the reviews they came from: by address and owner, one row standing for all of them, and it stays only for a verified review of that address, which gets its own score back. A second pass finds nothing to do
    /** @return int how many reviews got a score of their own, or would have on a dry run */
    public function rekeyLegacyRating(Review $review, bool $dryRun = false): int
    {
        $address = $this->addressKey($review);

        if (null === $address || $review->isVerified() || !$review->hasOwner()) {
            return 0;
        }

        $ownerType = (string) $review->getOwnerType();
        $ownerId = (int) $review->getOwnerId();

        if (!$this->ratingService->hasVote($ownerType, $ownerId, $address)) {
            return 0;
        }

        $scored = array_filter(
            $this->reviewRepository->findLocalByOwnerAndAddress($ownerType, $ownerId, (string) $review->getAuthorEmail()),
            static fn (Review $candidate): bool => ReviewStatus::Published === $candidate->getStatus() && null !== $candidate->getRating(),
        );
        $verified = array_filter($scored, static fn (Review $candidate): bool => $candidate->isVerified());
        $pending = array_filter(
            $scored,
            fn (Review $candidate): bool => !$candidate->isVerified() && !$this->ratingService->hasVote($ownerType, $ownerId, (string) $this->voterFor($candidate)),
        );

        if ($dryRun) {
            return \count($pending);
        }

        foreach ($pending as $candidate) {
            $this->ratingService->record($ownerType, $ownerId, $this->onSiteScale((int) $candidate->getRating()), (string) $this->voterFor($candidate));
        }

        if ([] === $verified) {
            $this->ratingService->withdraw($ownerType, $ownerId, $address);
        } elseif ([] !== $pending || !\in_array($review, $scored, true)) {
            // The review handed being taken out, nothing will carry its score elsewhere, and it may have written over this one
            $this->ratingService->record($ownerType, $ownerId, $this->onSiteScale((int) end($verified)->getRating()), $address);
        }

        return \count($pending);
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

    // The key a review's score is stored under in Rating, a hash holding nobody's address and null for an imported review, the platform having counted it: the address for a verified review, so two of them weigh once, a key of its own for any other, or typing someone's address would overwrite that person's score
    public function voterFor(Review $review): ?string
    {
        $address = $this->addressKey($review);

        if (null === $address || $review->isVerified()) {
            return $address;
        }

        return null === $review->getId() ? null : substr(hash('sha256', 'review|' . $review->getId()), 0, 32);
    }

    // The key of a review's address, null when it carries none or was not written here
    private function addressKey(Review $review): ?string
    {
        $email = $review->getAuthorEmail();

        if (!$review->isLocal() || null === $email || '' === trim($email)) {
            return null;
        }

        return substr(hash('sha256', strtolower(trim($email))), 0, 32);
    }
}
