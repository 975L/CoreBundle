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
     * "binned" says whether the owner of that usage is in the bin rather than deleted outright (a soft-deleted Page, say): the usage is real, so the media is still not free to be removed, but nothing on the live site draws it any more. It is what lets the library leave those medias out of its gallery and svg-fonts out of its run, rather than the alternative of dropping the usage altogether - which would make the media read as unused, and hand it to the delete button.
     *
     * Set it only if you know the answer, and then on every usage you report: an omitted key means "no verdict", not "live". BlockMediaUsageProvider omits it on purpose - it knows a media hangs off a block, never what owns that block, so a media it alone reports is left visible rather than hidden on a guess.
     *
     * @param Media[] $medias the Media rows to resolve, already loaded by the caller (avoids every provider re-querying them)
     *
     * @return array<int, list<array{label: string, url: ?string, binned?: bool}>> usages keyed by Media id, only for medias this provider recognizes as its own
     */
    public function getUsages(array $medias): array;
}
