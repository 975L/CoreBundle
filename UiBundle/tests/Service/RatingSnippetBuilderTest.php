<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Service\RatingSnippetBuilder;
use PHPUnit\Framework\TestCase;

// The AggregateRating node whatever the site lets its visitors vote on nests in its own graph
class RatingSnippetBuilderTest extends TestCase
{
    public function testATallyBecomesAnAggregateRatingNode(): void
    {
        $this->assertSame([
            '@type' => 'AggregateRating',
            'ratingValue' => '4.3',
            'ratingCount' => 12,
            'bestRating' => 5,
            'worstRating' => 1,
        ], $this->builder()->buildFromAggregate(['average' => 4.3, 'count' => 12]));
    }

    // No "@context" and no <script>: this is a property of the thing rated, nested by whoever owns it
    public function testTheNodeIsAFragmentAndNotAGraphOfItsOwn(): void
    {
        $this->assertArrayNotHasKey('@context', $this->builder()->buildFromAggregate(['average' => 4.3, 'count' => 12]));
    }

    // Nobody voted: an aggregate over no vote is what a search engine drops the whole rich result for
    public function testNoVoteAtAllPublishesNothing(): void
    {
        $this->assertSame([], $this->builder()->buildFromAggregate(['average' => 0.0, 'count' => 0]));
        $this->assertSame([], $this->builder()->buildFromAggregate([]));
    }

    // The scale the site rates on, and not the five schema.org assumes when the bounds are left out
    public function testTheSiteOwnScaleIsPublishedAsTheBestRating(): void
    {
        $this->assertSame(10, $this->builder(10)->buildFromAggregate(['average' => 9.6, 'count' => 3])['bestRating']);
    }

    // A site that lowered its scale keeps the votes cast on the old one, and an average above the bounds is an invalid node
    public function testAnAverageAboveTheScaleIsBroughtBackToIt(): void
    {
        $this->assertSame('5.0', $this->builder()->buildFromAggregate(['average' => 8.2, 'count' => 3])['ratingValue']);
    }

    // The same node read straight from the owner, for whoever holds no tally already
    public function testTheNodeCanBeReadFromTheOwnerItself(): void
    {
        $ratingService = $this->createStub(RatingService::class);
        $ratingService->method('getScale')->willReturn(5);
        $ratingService->method('getAggregate')->willReturn(['average' => 2.0, 'count' => 7]);

        $this->assertSame(7, new RatingSnippetBuilder($ratingService)->build('shop_product', 39)['ratingCount']);
    }

    private function builder(int $scale = 5): RatingSnippetBuilder
    {
        $ratingService = $this->createStub(RatingService::class);
        $ratingService->method('getScale')->willReturn($scale);

        return new RatingSnippetBuilder($ratingService);
    }
}
