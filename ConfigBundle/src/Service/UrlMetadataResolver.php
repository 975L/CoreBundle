<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Hands back what an url says of itself when no entity carries it (see UrlMetadata). Read by the layouts through the "url_metadata" Twig function, and by whatever else needs the same text - a SitemapProviderInterface declaring a listing, say, which then states in llms.txt exactly what the page states in its meta. Only the path => id map is cached, under CACHE_TAG (cleared by CacheTagListener): an url without a row - most of them - costs no query at all, one with a row costs a lookup by primary key, and the entity comes back live with its ogImage rather than as a ghost unserialized from the pool
class UrlMetadataResolver
{
    // The tag a {% cache %} fragment printing url_metadata() carries, so an edited row reaches it
    public const string CACHE_TAG = 'url_metadata';

    private const string CACHE_KEY = 'c975l_url_metadata_ids';

    /** @var array<string, int>|null */
    private ?array $ids = null;

    /** @var array<string, UrlMetadata|null> */
    private array $rows = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlMetadataRepository $urlMetadataRepository,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    // The row describing the page being rendered, null outside an http request - the layouts are shared with the emails, which are rendered from a console command and have no path at all
    public function forCurrentRequest(): ?UrlMetadata
    {
        $request = $this->requestStack->getCurrentRequest();

        return null === $request ? null : $this->forPath($request->getPathInfo());
    }

    public function forPath(string $path): ?UrlMetadata
    {
        $path = $this->normalize($path);
        $id = $this->ids()[$path] ?? null;
        if (null === $id) {
            return null;
        }

        return \array_key_exists($path, $this->rows)
            ? $this->rows[$path]
            : $this->rows[$path] = $this->urlMetadataRepository->find($id);
    }

    /** @return array<string, int> */
    private function ids(): array
    {
        if (null !== $this->ids) {
            return $this->ids;
        }

        // The table is created by the app, not by a migration this bundle ships (same as site_redirect), so it may legitimately not exist yet on a site that has not run doctrine:migrations:migrate since the update. A missing description is worth a health check row, never a 500 on every page of the site - and the empty answer is not cached, so the migration is seen on the next request
        try {
            $this->ids = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->tag(self::CACHE_TAG);

                return $this->urlMetadataRepository->findIdsIndexedByPath();
            });
        } catch (TableNotFoundException) {
            $this->ids = [];
        }

        return $this->ids;
    }

    // Same normalisation UrlMetadata::setPath() applies on the way in, so a lookup can only miss because no row was written
    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
