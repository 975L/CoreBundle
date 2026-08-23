<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Repository\ReviewRepository;

// Exposes the published reviews to the generic "collection" block, so no block kind of its own is needed for them - an editor picks "Avis" as the source of a collection already on the page
// Every published review whatever its owner: this is the "what people say about us" wall, where the reviews of one book are shown on that book's page instead (see ReviewRuntime)
class ReviewCollectionSourceProvider implements CollectionSourceProviderInterface
{
    public const string CACHE_TAG = 'ui_reviews';

    private const string ITEM_TEMPLATE = '@c975LUi/collection/ReviewItem.html.twig';

    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewService $reviewService,
    ) {
    }

    public function getSources(): array
    {
        // Same setting as the management screen (see MenuProvider): a site that has turned the reviews off must not keep showing them, and a collection already pointing here renders empty rather than breaking (CollectionSourceRegistry ignores an unknown source)
        if (!$this->reviewService->isEnabled()) {
            return [];
        }

        return [
            'ui.collection.reviews' => [
                'label' => 'label.reviews_collection_source',
                'count' => fn (): int => $this->reviewRepository->getAggregate()['count'],
                'items' => $this->buildItems(...),
                'cacheTags' => [self::CACHE_TAG],
                'itemTemplate' => self::ITEM_TEMPLATE,
            ],
        ];
    }

    // An array rather than a generator: CollectionSourceRegistry promises one, and the collection block shuffles it and reads its first and last keys
    /**
     * @return CollectionItem[]
     */
    private function buildItems(?int $limit): array
    {
        return array_map(self::buildItem(...), $this->reviewRepository->findForDisplay(null, $limit));
    }

    // The rating, the date and the reply travel in "data": the built-in card knows none of them, and ReviewItem.html.twig - which this source names as its own template - reads them from there
    public static function buildItem(Review $review): CollectionItem
    {
        return new CollectionItem(
            // Empty rather than null, CollectionItem taking a plain string - ReviewItem.html.twig prints its own "anonymous" label in its place
            title: $review->getAuthorName() ?? '',
            description: $review->getComment(),
            imageUrl: $review->getAuthorAvatarUrl(),
            url: $review->getSourceUrl(),
            data: [
                'id' => $review->getId(),
                'rating' => $review->getRating(),
                'publishedAt' => $review->getPublishedAt(),
                'replyComment' => $review->getReplyComment(),
                'repliedAt' => $review->getRepliedAt(),
                'source' => $review->getSource(),
                'verified' => $review->isVerified(),
            ],
        );
    }
}
