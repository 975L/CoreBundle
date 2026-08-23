<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewService;
use c975L\UiBundle\Twig\ReviewRuntime;
use PHPUnit\Framework\TestCase;

// What a page asks for to draw the reviews of the thing it is about
class ReviewRuntimeTest extends TestCase
{
    // The very card the review wall draws its own from, so a book's reviews and the site's never drift apart
    public function testReviewsComeBackAsTheCollectionItemTheWallAlreadyDraws(): void
    {
        $items = $this->runtime(true, $this->review())->reviews('book', 12);

        $this->assertCount(1, $items);
        $this->assertInstanceOf(CollectionItem::class, $items[0]);
        $this->assertSame('Jean D.', $items[0]->title);
        $this->assertSame('Impeccable', $items[0]->description);
        $this->assertSame(4, $items[0]->data['rating']);
    }

    // A template asking for them on a site collecting none draws nothing at all, where a missing function would break the page it was added to
    public function testNothingComesBackWhileTheFeatureIsOff(): void
    {
        $this->assertSame([], $this->runtime(false, $this->review())->reviews('book', 12));
    }

    // The repository is not even reached: a site that collects no reviews runs no query to say so
    public function testTheRepositoryIsNotQueriedWhileTheFeatureIsOff(): void
    {
        $repository = $this->createMock(ReviewRepository::class);
        $repository->expects($this->never())->method('findForOwner');

        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn(false);

        new ReviewRuntime($repository, $reviewService)->reviews('book', 12);
    }

    // What a template reads to decide on its own whether to draw the section and its "leave a review" link
    public function testTheFeatureSwitchIsReadableOnItsOwn(): void
    {
        $this->assertTrue($this->runtime(true)->reviewsEnabled());
        $this->assertFalse($this->runtime(false)->reviewsEnabled());
    }

    private function review(): Review
    {
        return new Review()
            ->setAuthorName('Jean D.')
            ->setRating(4)
            ->setComment('Impeccable')
        ;
    }

    private function runtime(bool $enabled, Review ...$reviews): ReviewRuntime
    {
        $repository = $this->createStub(ReviewRepository::class);
        $repository->method('findForOwner')->willReturn($reviews);

        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn($enabled);

        return new ReviewRuntime($repository, $reviewService);
    }
}
