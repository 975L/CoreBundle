<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

// One folder or file a bundle declares as irreplaceable, as returned by a BackupPathProviderInterface
// #[Exclude] because services.yaml registers all of src/ as public autowired services, and a value object with scalar arguments can't be autowired: without it, every app installing the bundle fails to compile its container
#[Exclude]
class BackupPath
{
    // Small, versioned, and rebuilt from nothing: it goes into the dated archive of every run, so its history is kept. What is neither in git nor in the database belongs here - .env.local being the case that stops a restored server from starting
    public const MODE_ARCHIVE = 'archive';

    // Large and written once: mirrored as-is, never compressed and never dated. A photo does not need a version history, it needs a copy - and bzip2 gains about 1% on a JPEG while costing an hour on nine gigabytes
    public const MODE_MIRROR = 'mirror';

    public function __construct(
        // Relative to the project directory ('public/medias'), which is what the archive stores, what the manifest publishes and what a restore has to name
        public readonly string $path,
        public readonly string $mode = self::MODE_ARCHIVE,
    ) {
    }
}
