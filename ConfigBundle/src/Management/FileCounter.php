<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// How many files a declared path holds, shared by the two commands that need the number for different reasons: BackupCommand reports it, BackupOffsiteCommand sizes its deletion guard on it. Static and stateless like ByteFormatter, the count depending on nothing but the path.
//
// Zero for a path that isn't there, rather than the exception a RecursiveDirectoryIterator raises: the callers read the number to decide something, and "no file" is the right answer for both of them - a folder that vanished is precisely when the guard has to be at its tightest.
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
