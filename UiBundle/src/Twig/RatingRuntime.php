<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Service\RatingService;
use Twig\Extension\RuntimeExtensionInterface;

// Holds RatingExtension's dependencies (see it for why they live apart)
class RatingRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RatingService $ratingService,
        private readonly RatingRepository $ratingRepository,
    ) {
    }

    /**
     * What components/Rating/Rating.html.twig needs to draw itself: the public tally, plus the shape the site chose for it.
     *
     * The voter's own score is deliberately absent - the page carrying this is cached and shared between visitors, so what one of them voted is read from their own browser by assets/js/rating.js, never printed here.
     *
     * $aggregate is the tally already read for this owner, which a listing hands over rather than letting each of its cards query its own (see ratings()). Anything else than the shape ratings() returns is read as no vote at all, a card of a catalog being no place to raise an error.
     *
     * @param array{average?: float, count?: int}|null $aggregate the keys are optional because this comes from a template, where an owner nobody voted on can be handed over as an empty array
     *
     * @return array{ownerType: string, ownerId: int, average: float, count: int, scale: int, icon: string}
     */
    public function rating(string $ownerType, int $ownerId, ?int $scale = null, ?string $icon = null, ?array $aggregate = null): array
    {
        return [
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
            'scale' => $this->ratingService->getScale($scale),
            'icon' => $this->ratingService->getIcon($icon),
        ] + (null === $aggregate
            ? $this->ratingService->getAggregate($ownerType, $ownerId)
            : ['average' => (float) ($aggregate['average'] ?? 0.0), 'count' => (int) ($aggregate['count'] ?? 0)]);
    }

    /**
     * The same tallies for a whole list at once, for a catalog page printing one row of stars per card: thirty cards calling ui_rating() would run thirty queries.
     *
     * @param int[] $ownerIds
     *
     * @return array<int, array{average: float, count: int}> keyed by owner id, ids nobody voted on carrying a zeroed tally rather than being left out
     */
    public function ratings(string $ownerType, array $ownerIds): array
    {
        $aggregates = $this->ratingRepository->getAggregates($ownerType, $ownerIds);

        foreach ($ownerIds as $ownerId) {
            $aggregates[$ownerId] ??= ['average' => 0.0, 'count' => 0];
        }

        return $aggregates;
    }
}
