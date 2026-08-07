<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

// Gathers what every installed bundle declared through BackupPathProviderInterface, so neither the backup command nor the offsite one iterates providers of its own
class BackupPathCollector
{
    public function __construct(
        /** @var iterable<BackupPathProviderInterface> */
        private readonly iterable $backupPathProviders,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    // The declared paths of one mode, deduplicated and restricted to what is actually on disk: two bundles naming the same folder must not archive it twice, and an install without private/medias must not be told its backup failed
    /** @return string[] paths relative to the project directory */
    public function getPaths(string $mode): array
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $paths = [];

        foreach ($this->backupPathProviders as $provider) {
            foreach ($provider->getBackupPaths() as $backupPath) {
                // A path escaping the project would be archived under a name no restore could place back, and is far more likely to be a typo than an intent
                $relative = trim($backupPath->path, '/');
                if ($mode !== $backupPath->mode || '' === $relative || str_contains($relative, '..')) {
                    continue;
                }

                if (!in_array($relative, $paths, true) && file_exists($projectDir . '/' . $relative)) {
                    $paths[] = $relative;
                }
            }
        }

        sort($paths);

        return $this->dropNested($paths);
    }

    // A folder already covered by a declared ancestor is dropped. Deduplicating equal paths isn't enough: an app
    // declaring public/medias while GalleryBundle declares public/medias/gallery would have the gallery mirrored
    // twice, to two different places at the destination - and counted twice in what the run reports holding
    /** @param string[] $paths sorted, so an ancestor always precedes what it covers */
    private function dropNested(array $paths): array
    {
        $kept = [];

        foreach ($paths as $path) {
            foreach ($kept as $ancestor) {
                if (str_starts_with($path, $ancestor . '/')) {
                    continue 2;
                }
            }

            $kept[] = $path;
        }

        return $kept;
    }
}
