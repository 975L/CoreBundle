<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Resolves a file path an import manifest names inside its own extracted files folder (see ImportProviderInterface::import()'s $filesDir) - the one check every importer handing such a path to Vich or to a copy goes through, since a manifest is content anyone uploading an archive writes
final class ArchivePath
{
    // The real path of the file $relative names under $filesDir, or null for anything else: not a string, an absolute path, a ".." segment, a NUL byte, a file that does not exist, or one a symlink takes out of the folder
    public static function resolve(string $filesDir, mixed $relative): ?string
    {
        if (!\is_string($relative) || '' === $relative || str_starts_with($relative, '/') || str_contains($relative, "\0") || \in_array('..', explode('/', str_replace('\\', '/', $relative)), true)) {
            return null;
        }

        $root = realpath($filesDir);
        $path = realpath($filesDir . '/' . $relative);

        return false !== $root && false !== $path && is_file($path) && str_starts_with($path, $root . '/') ? $path : null;
    }
}
