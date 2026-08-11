<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\UiBundle\Entity\Media;

// Where this bundle's uploads land, as UiMediaNamer writes them. A path that isn't on disk is skipped, so an install using none of these declares nothing extra and reports nothing missing
class UiBackupPathProvider implements BackupPathProviderInterface
{
    // A role with no fixed icon spec keeps whatever it was uploaded as: raster is converted to webp by the namer, and anything else (an SVG logo) keeps its own extension. Both are declared, the one that isn't on disk being skipped
    private const array FREE_EXTENSIONS = ['webp', 'svg'];

    public function getBackupPaths(): array
    {
        $paths = [
            // Media's role and block uploads (Media::getVichMediaPath), shared with SiteBundle's own CollectionItem
            new BackupPath('public/medias/site', BackupPath::MODE_MIRROR),
            // Font's uploaded files (Font::getVichMediaPath)
            new BackupPath('public/medias/fonts', BackupPath::MODE_MIRROR),
        ];

        // The site-wide singleton graphics, which UiMediaNamer deliberately writes at the root of public/ under the role's own name rather than under medias/ - so no folder declaration reaches them, and a backup that only looked at medias/ silently left a site with no favicon and no logo to be restored from. Archived rather than mirrored: a handful of small files, worth a history, and cheap to carry on every run.
        // Read off the roles themselves so a role added later (the watermarks were, and went unbacked) is covered
        foreach (Media::getSingletonRoles() as $role) {
            $spec = Media::getFixedIconSpecs()[$role] ?? null;
            $extensions = null !== $spec ? [$spec['format']] : self::FREE_EXTENSIONS;

            foreach ($extensions as $extension) {
                $paths[] = new BackupPath(sprintf('public/%s.%s', $role, $extension), BackupPath::MODE_ARCHIVE);
            }
        }

        return $paths;
    }
}
