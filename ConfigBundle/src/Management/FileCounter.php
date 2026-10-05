<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// How many files a declared path holds, for BackupCommand's report: static and stateless like ByteFormatter, and zero for a path that isn't there rather than the exception a RecursiveDirectoryIterator raises
class FileCounter
{
    public static function count(string $path): int
    {
        if (is_file($path)) {
            return 1;
        }

        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                ++$count;
            }
        }

        return $count;
    }
}
