<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use Doctrine\ORM\EntityManagerInterface;

// Creates an empty row for every path a bundle declares and that has none yet (see UrlMetadataProviderInterface), so nobody ever types a path by hand in the back office - a slash apart, a hand-typed one would silently describe an url that does not exist.
// Creates only: a row already written is left untouched, and a row whose path is no longer declared is reported rather than deleted. An url can disappear from a listing for a release and come back, and the sentence written for it is work no synchronisation may throw away - it is removed by hand, from the screen
class UrlMetadataSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        // Every UrlMetadataProviderInterface implementation, whatever the bundle it comes from - tagged automatically by TaggedInterfacePass, so nothing has to be listed by hand in the app (see services.yaml)
        private readonly iterable $urlMetadataProviders,
        private readonly UrlMetadataRepository $urlMetadataRepository,
    ) {
    }

    /**
     * @return array{created: list<string>, orphaned: list<string>, declared: int}
     */
    public function synchronize(): array
    {
        $declared = $this->getDeclaredPaths();
        $existing = $this->urlMetadataRepository->findAllIndexedByPath();

        $created = [];
        foreach ($declared as $path) {
            if (isset($existing[$path])) {
                continue;
            }

            $this->em->persist(new UrlMetadata()->setPath($path));
            $created[] = $path;
        }

        if ([] !== $created) {
            $this->em->flush();
        }

        return [
            'created' => $created,
            'orphaned' => array_values(array_diff(array_keys($existing), $declared)),
            'declared' => count($declared),
        ];
    }

    // Normalised and deduplicated: two bundles may legitimately declare the same url - a listing one of them serves and the other links to - and the unique constraint would refuse the second row
    /** @return list<string> */
    private function getDeclaredPaths(): array
    {
        $paths = [];
        /** @var UrlMetadataProviderInterface $provider */
        foreach ($this->urlMetadataProviders as $provider) {
            foreach ($provider->getUrlMetadataPaths() as $path) {
                $paths[] = '/' . trim($path, '/');
            }
        }

        return array_values(array_unique($paths));
    }
}
