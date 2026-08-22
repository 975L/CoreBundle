<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Builds the AggregateRating node of whatever the site lets its visitors vote on, out of the very tally components/Rating/Rating.html.twig prints.
// A fragment and not a graph of its own, which is why nothing here emits a "@context" nor a <script>: schema.org reads an aggregate rating as a property of the thing rated - a Product, a Book, an Event - so it is the bundle owning that thing which nests this in its own node (see ShopBundle's ProductSnippetBuilder). Published on its own, it would rate nothing.
class RatingSnippetBuilder
{
    // The lowest score a visitor can give: the widget's first icon, whatever the scale the site chose above it
    private const int WORST_RATING = 1;

    public function __construct(private readonly RatingService $ratingService)
    {
    }

    // The node for one owner, read from the same aggregate the widget displays
    public function build(string $ownerType, int $ownerId, ?int $scale = null): array
    {
        return $this->buildFromAggregate($this->ratingService->getAggregate($ownerType, $ownerId), $scale);
    }

    /**
     * The same node from a tally already read - a listing holding the aggregates of all its cards (see RatingRuntime::ratings()) has no query left to run here.
     *
     * @param array{average?: float, count?: int} $aggregate the keys are optional, an owner nobody voted on being handed over as an empty array
     */
    public function buildFromAggregate(array $aggregate, ?int $scale = null): array
    {
        $count = (int) ($aggregate['count'] ?? 0);

        // Nobody voted: an AggregateRating over no vote is what Google rejects the whole rich result for, so the thing rated publishes no rating at all rather than a zeroed one
        if ($count < 1) {
            return [];
        }

        $best = $this->ratingService->getScale($scale);

        return [
            '@type' => 'AggregateRating',
            // Clamped to the scale it is read against: a site that lowered its scale keeps votes cast on the old one, and an average above "bestRating" is an invalid node
            'ratingValue' => number_format(min((float) $best, max((float) self::WORST_RATING, (float) ($aggregate['average'] ?? 0.0))), 1, '.', ''),
            'ratingCount' => $count,
            // Both bounds are stated rather than left out: schema.org assumes a scale of 1 to 5 when they are absent, where this bundle's own scale goes up to ten
            'bestRating' => $best,
            'worstRating' => self::WORST_RATING,
        ];
    }
}
