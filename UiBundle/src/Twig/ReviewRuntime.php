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
use c975L\UiBundle\Service\ReviewTokenSigner;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Markup;

// Holds ReviewExtension's dependencies (see it for why they live apart)
class ReviewRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewService $reviewService,
        private readonly ReviewTokenSigner $tokenSigner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly TagAwareCacheInterface $cache,
        private readonly RequestStack $requestStack,
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

    /**
     * The whole reviews section of one listed thing, rendered once and kept - the same cache the blocks of the page around it are held in, tagged so that publishing a review empties it (see ReviewCacheInvalidationListener).
     *
     * Nothing in it is per-visitor: the published reviews, and a fold whose form is fetched separately and never printed here (see components/Review/List.html.twig). What used to be a query on every single display of every sheet is now one query per change.
     *
     * Rendered outside any http request - a console command, a messenger worker - it is rendered and not stored, the locale of that render being nobody's: the same reading as the blocks' own cache (see BlockExtension).
     */
    public function section(string $ownerType, int $ownerId): Markup
    {
        if (!$this->reviewService->isEnabled()) {
            return new Markup('', 'UTF-8');
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return new Markup($this->render($ownerType, $ownerId), 'UTF-8');
        }

        $html = $this->cache->get(
            sprintf('ui_reviews_section_%s_%d_%s', $ownerType, $ownerId, $request->getLocale()),
            function (ItemInterface $item) use ($ownerType, $ownerId): string {
                $item->expiresAfter(null);
                // The tag the listener empties on every review written, imported or moderated - the section of one sheet is rebuilt on the next display and no sooner
                $item->tag([ReviewCollectionSourceProvider::CACHE_TAG]);

                return $this->render($ownerType, $ownerId);
            }
        );

        return new Markup($html, 'UTF-8');
    }

    // Where a visitor writes about one thing - built here rather than with a path() call, the route naming what is reviewed through a signed token and never through its id (see ReviewTokenSigner)
    public function url(string $ownerType, int $ownerId): string
    {
        return $this->urlGenerator->generate('ui_review_new', ['token' => $this->tokenSigner->sign($ownerType, $ownerId)]);
    }

    private function render(string $ownerType, int $ownerId): string
    {
        return $this->twig->render('@c975LUi/components/Review/List.html.twig', [
            'reviews' => $this->reviews($ownerType, $ownerId),
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
        ]);
    }
}
