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

// Hands back what an url says of itself when no entity carries it (see UrlMetadata). Read by the layouts through the "url_metadata" Twig function, and by whatever else needs the same text - a SitemapProviderInterface declaring a listing, say, which then states in llms.txt exactly what the page states in its meta.
// The whole table is loaded on the first lookup of a request rather than queried path by path: the rows are few, a page asks for its own row once, and this keeps a shared layout from turning into one query per render
class UrlMetadataResolver
{
    /** @var array<string, UrlMetadata>|null */
    private ?array $rows = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlMetadataRepository $urlMetadataRepository,
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
        return $this->all()[$this->normalize($path)] ?? null;
    }

    /** @return array<string, UrlMetadata> */
    private function all(): array
    {
        if (null !== $this->rows) {
            return $this->rows;
        }

        // The table is created by the app, not by a migration this bundle ships (same as site_redirect), so it may legitimately not exist yet on a site that has not run doctrine:migrations:migrate since the update. A missing description is worth a health check row, never a 500 on every page of the site
        try {
            $this->rows = $this->urlMetadataRepository->findAllIndexedByPath();
        } catch (TableNotFoundException) {
            $this->rows = [];
        }

        return $this->rows;
    }

    // Same normalisation UrlMetadata::setPath() applies on the way in, so a lookup can only miss because no row was written
    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
