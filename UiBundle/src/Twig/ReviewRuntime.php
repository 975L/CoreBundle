<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewCollectionSourceProvider;
use c975L\UiBundle\Service\ReviewService;
use Twig\Extension\RuntimeExtensionInterface;

// Holds ReviewExtension's dependencies (see it for why they live apart)
class ReviewRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewService $reviewService,
    ) {
    }

    /**
     * The published reviews of one thing, as the very CollectionItem the review wall draws its own cards from - so a book's reviews and the site's look alike without a second card to keep in step.
     *
     * Empty on a site that collects no reviews, rather than absent: a template asking for them then draws nothing at all, where a missing function would break the page it was added to.
     *
     * @return CollectionItem[]
     */
    public function reviews(string $ownerType, int $ownerId, ?int $limit = null): array
    {
        if (!$this->reviewService->isEnabled()) {
            return [];
        }

        return array_map(
            ReviewCollectionSourceProvider::buildItem(...),
            $this->reviewRepository->findForOwner($ownerType, $ownerId, $limit)
        );
    }

    // Whether the site collects reviews at all, for a template deciding on its own whether to draw the section and its "leave a review" link
    public function reviewsEnabled(): bool
    {
        return $this->reviewService->isEnabled();
    }
}
