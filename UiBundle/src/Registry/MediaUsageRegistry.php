<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Media;

class MediaUsageRegistry
{
    /** @var MediaUsageProviderInterface[] */
    private array $providers = [];

    public function addProvider(MediaUsageProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Merges usages contributed by every provider for the given Media rows
    public function getUsages(array $medias): array
    {
        $usages = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getUsages($medias) as $mediaId => $links) {
                $usages[$mediaId] = array_merge($usages[$mediaId] ?? [], $links);
            }
        }

        return $usages;
    }

    /**
     * The ids of those medias every owner of which is in the bin - drawn by nothing the site serves, yet used, so neither deletable nor worth showing.
     *
     * Only the usages carrying the "binned" key are read: a provider omitting it has no verdict to give (see MediaUsageProviderInterface), and counting its usage as live would hide the answer of the one provider that does know. So the rule is: at least one owner in the bin, and no owner outside it. A media used nowhere at all is never one of them - it has no owner to be in the bin, and hiding it would be hiding the very rows the library exists to let an admin find again.
     *
     * @param Media[] $medias
     *
     * @return list<int>
     */
    public function getBinnedOnlyMediaIds(array $medias): array
    {
        $binnedOnly = [];
        foreach ($this->getUsages($medias) as $mediaId => $links) {
            $verdicts = array_column($links, 'binned');

            if ([] !== $verdicts && array_all($verdicts, static fn (bool $binned): bool => $binned)) {
                $binnedOnly[] = $mediaId;
            }
        }

        return $binnedOnly;
    }
}
