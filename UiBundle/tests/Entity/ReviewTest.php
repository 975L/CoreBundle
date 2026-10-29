<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use PHPUnit\Framework\TestCase;

class ReviewTest extends TestCase
{
    public function testToStringPrintsTheAuthorAndTheRating(): void
    {
        $review = new Review()
            ->setAuthorName('Jean D.')
            ->setRating(4)
        ;

        $this->assertSame('Jean D. (4/5)', (string) $review);
    }

    // A platform can serve a review with no display name, and the label printed in its place belongs to the card, not to the entity
    public function testToStringLeavesTheAuthorBlankWhenTheSourceServedNone(): void
    {
        $this->assertSame(' (5/5)', (string) new Review()->setRating(5));
    }

    // Verified is the default because L111-7-2 asks a site to say which reviews are verified, and a source only ever lowers it
    public function testAReviewIsVerifiedUntilASourceSaysOtherwise(): void
    {
        $this->assertTrue(new Review()->isVerified());
        $this->assertFalse(new Review()->setVerified(false)->isVerified());
    }

    public function testIdIsNullUntilTheReviewIsPersisted(): void
    {
        $this->assertNull(new Review()->getId());
    }

    public function testTheImportedFieldsAreReadBackAsTheyWereSet(): void
    {
        $publishedAt = new \DateTimeImmutable('2026-08-01 10:00:00');
        $repliedAt = new \DateTimeImmutable('2026-08-02 09:00:00');

        $review = new Review()
            ->setSource('google')
            ->setExternalId('accounts/1/locations/2/reviews/3')
            ->setAuthorName('Jean D.')
            ->setAuthorAvatarUrl('https://example.org/avatar.png')
            ->setRating(4)
            ->setComment('Impeccable')
            ->setPublishedAt($publishedAt)
            ->setReplyComment('Merci !')
            ->setRepliedAt($repliedAt)
            ->setSourceUrl('https://maps.google.com/review/3')
        ;

        $this->assertSame('google', $review->getSource());
        $this->assertSame('accounts/1/locations/2/reviews/3', $review->getExternalId());
        $this->assertSame('Jean D.', $review->getAuthorName());
        $this->assertSame('https://example.org/avatar.png', $review->getAuthorAvatarUrl());
        $this->assertSame(4, $review->getRating());
        $this->assertSame('Impeccable', $review->getComment());
        $this->assertSame($publishedAt, $review->getPublishedAt());
        $this->assertSame('Merci !', $review->getReplyComment());
        $this->assertSame($repliedAt, $review->getRepliedAt());
        $this->assertSame('https://maps.google.com/review/3', $review->getSourceUrl());
    }

    // A rating with no text is a review too, and a withdrawn reply has to be storable as absent rather than empty
    public function testTheOptionalFieldsAreNullable(): void
    {
        $review = new Review()
            ->setAuthorName(null)
            ->setAuthorAvatarUrl(null)
            ->setComment(null)
            ->setReplyComment(null)
            ->setRepliedAt(null)
            ->setSourceUrl(null)
        ;

        $this->assertNull($review->getAuthorName());
        $this->assertNull($review->getAuthorAvatarUrl());
        $this->assertNull($review->getComment());
        $this->assertNull($review->getReplyComment());
        $this->assertNull($review->getRepliedAt());
        $this->assertNull($review->getSourceUrl());
    }

    // The list's switch reads the status as a yes/no: only a published review is displayed
    public function testOnlyAPublishedReviewIsDisplayed(): void
    {
        $this->assertFalse(new Review()->isDisplayed());
        $this->assertTrue(new Review()->setStatus(ReviewStatus::Published)->isDisplayed());
        $this->assertFalse(new Review()->setStatus(ReviewStatus::Rejected)->isDisplayed());
    }

    // Switched on, a pending review is published; switched off, it is turned down rather than sent back to the moderators' queue
    public function testSwitchingDisplayedPublishesOrRejects(): void
    {
        $this->assertSame(ReviewStatus::Published, new Review()->setDisplayed(true)->getStatus());
        $this->assertSame(ReviewStatus::Rejected, new Review()->setStatus(ReviewStatus::Published)->setDisplayed(false)->getStatus());
    }
}
