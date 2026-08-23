<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;

// One written review, whether a visitor left it here or a platform was asked for it (see c975L\SocialBundle\Contract\ReviewsSourceInterface). The two used to be different things in different bundles and shared ten columns out of eleven; what actually separates them is $source, and what a page displays is the same card either way
// Lives next to Rating rather than with it: a rating is one anonymous click, revocable and never moderated, where a review carries a name, a text and a decision to publish. Same owner vocabulary though (see $ownerType), so a book gathers both without mapping either
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'site_review')]
#[ORM\UniqueConstraint(name: 'uniq_review_source_external', columns: ['source', 'external_id'])]
#[ORM\Index(name: 'idx_review_published', columns: ['published_at'])]
#[ORM\Index(name: 'idx_review_owner', columns: ['owner_type', 'owner_id'])]
#[ORM\Index(name: 'idx_review_status', columns: ['status'])]
class Review implements \Stringable
{
    // What $source holds for a review written here, and the one value no ReviewsSourceInterface may declare: a sync matches on $source, so the rows this site owns are the rows no sync can reach
    public const string SOURCE_SITE = 'site';

    // Five, whatever the site's ui-rating-scale says: a review is scored out of five here as it is on the platforms it is imported from, and the two would otherwise be shown side by side on scales nothing records. The ratings are the other thing, and follow the site's setting (see RatingService)
    public const int SCALE = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Where the review comes from: self::SOURCE_SITE, or the name the platform declares itself under (see ReviewsSourceInterface::getName())
    #[ORM\Column(length: 50)]
    private string $source = self::SOURCE_SITE;

    // The source's own identifier, what makes a re-sync update rather than duplicate. Null for a review written here, which nothing external names - the unique index above tolerates as many of those as the site collects, a null never colliding with a null
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    // What the review is about, in the vocabulary Rating already uses ("book", "gallery_media"...), so the same word answers for the score and for the text. Null when the review is about the site itself rather than about one of the things it lists, which is what a Google Business Profile review is
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ownerType = null;

    #[ORM\Column(nullable: true)]
    private ?int $ownerId = null;

    #[ORM\Column(length: 20, enumType: ReviewStatus::class)]
    private ReviewStatus $status = ReviewStatus::Pending;

    // Nullable because a platform can hand back a review with no display name; the card then prints its own "anonymous" label rather than the site inventing one at import time
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorName = null;

    // Only ever filled for a review written here, and never displayed: it is how the site answers the person who wrote it, and how their score is kept apart from anyone else's (see ReviewService::voterFor())
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorEmail = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $authorAvatarUrl = null;

    // Nullable in both directions: a platform returns plenty of ratings with no text, and a visitor here may want to say something without scoring anything
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    // When the review was written, not when it was let through: a moderated review keeps the date its author gave it, which is the date L111-7-2 asks the site to display
    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    // The owner's public answer, the only part of an imported review this site may write - pushed back to the source when it supports it (see c975L\UiBundle\Contract\ReviewReplyPublisherInterface)
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $replyComment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $repliedAt = null;

    // Deep link to the review on the source, required by Google's attribution rules and a proof of authenticity for the visitor
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $sourceUrl = null;

    // Whether the review is tied to a real transaction or account; displayed as such, L111-7-2 requiring the site to say which reviews are verified. False by default for a review written here, where nothing proves the person ever read the book
    #[ORM\Column(options: ['default' => true])]
    private bool $verified = true;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->authorName ?? '', null === $this->rating ? '-' : $this->rating . '/' . self::SCALE);
    }

    // Whether the review was written here rather than imported, which is what decides if it can be edited, moderated and counted into an owner's average
    public function isLocal(): bool
    {
        return self::SOURCE_SITE === $this->source;
    }

    // Whether the review is attached to one listed thing rather than to the site as a whole
    public function hasOwner(): bool
    {
        return null !== $this->ownerType && null !== $this->ownerId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function setOwnerType(?string $ownerType): self
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function setOwnerId(?int $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getStatus(): ReviewStatus
    {
        return $this->status;
    }

    public function setStatus(ReviewStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): self
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): self
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    public function getAuthorAvatarUrl(): ?string
    {
        return $this->authorAvatarUrl;
    }

    public function setAuthorAvatarUrl(?string $authorAvatarUrl): self
    {
        $this->authorAvatarUrl = $authorAvatarUrl;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getReplyComment(): ?string
    {
        return $this->replyComment;
    }

    public function setReplyComment(?string $replyComment): self
    {
        $this->replyComment = $replyComment;

        return $this;
    }

    public function getRepliedAt(): ?\DateTimeImmutable
    {
        return $this->repliedAt;
    }

    public function setRepliedAt(?\DateTimeImmutable $repliedAt): self
    {
        $this->repliedAt = $repliedAt;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;

        return $this;
    }
}
