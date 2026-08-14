<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Attribute\AsTwigFunction;

class MediaExtension
{
    // Shared with BlockCacheInvalidationListener, which invalidates this same tag on a singleton-role Media save/removal
    public const MEDIA_SINGLETONS_CACHE_TAG = 'media_singletons';

    // Per-role memoization: a base layout typically calls site_media() for the same role (logo, favicon...) from several places (header, footer, meta tags) - keeps that to one query per role per request instead of one per call. Not applied to getRandomSiteMedia(): "random" means a fresh pick is the whole point, memoizing it would defeat that
    private array $requestCache = [];
    private bool $singletonRolesPreloaded = false;

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    #[AsTwigFunction('site_media')]
    public function getSiteMedia(string $role): ?Media
    {
        $this->preloadSingletonRoles();

        if (!array_key_exists($role, $this->requestCache)) {
            $this->requestCache[$role] = $this->mediaRepository->findOneByRole($role);
        }

        return $this->requestCache[$role];
    }

    // Site-wide singleton roles (logo, favicon...) are a small, fixed set (see Media::getSingletonRoles()), read on every page and barely ever changed - cached across requests (invalidated by BlockCacheInvalidationListener whenever a singleton-role Media is saved/removed), on top of the per-request memoization above so a hit still costs zero queries. Caching the Media entities directly is safe here: these rows are never attached to a Block (see Media::$block's own comment) so the only other relation (owning $user) never gets resolved by anything rendering site_media()/site_random_media(), and stays an untouched, harmless lazy reference through the cache round-trip
    private function preloadSingletonRoles(): void
    {
        if ($this->singletonRolesPreloaded) {
            return;
        }
        $this->singletonRolesPreloaded = true;

        $medias = $this->cache->get(self::MEDIA_SINGLETONS_CACHE_TAG, function (ItemInterface $item): array {
            $item->expiresAfter(null);
            $item->tag([self::MEDIA_SINGLETONS_CACHE_TAG]);

            return $this->mediaRepository->findBySingletonRoles();
        });

        foreach ($medias as $media) {
            $this->requestCache[$media->getRole()] = $media;
        }

        // A singleton role with no row yet (e.g. no favicon uploaded) must still be memoized as null, otherwise getSiteMedia() would keep re-querying it individually on every call
        foreach (Media::getSingletonRoles() as $role) {
            $this->requestCache[$role] ??= null;
        }
    }

    #[AsTwigFunction('site_random_media')]
    public function getRandomSiteMedia(string $role): ?Media
    {
        return $this->mediaRepository->findRandomByRole($role);
    }
}
