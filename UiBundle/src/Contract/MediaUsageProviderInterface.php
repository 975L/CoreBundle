<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Entity\Media;

// Lets any bundle declare where its own entities use a given Media (e.g. SiteBundle knows a Media is a Page's og-image or a site-wide graphic role, UiBundle only knows a Media is attached to a Block). Implement this and the service is auto-discovered by MediaUsageProviderPass - see Readme.
interface MediaUsageProviderInterface
{
    /**
     * @param Media[] $medias the Media rows to resolve, already loaded by the caller (avoids every provider re-querying them)
     *
     * @return array<int, list<array{label: string, url: ?string}>> usages keyed by Media id, only for medias this provider recognizes as its own
     */
    public function getUsages(array $medias): array;
}
