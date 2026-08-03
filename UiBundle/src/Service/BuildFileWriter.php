<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// The one way a listener drops a generated stylesheet into public/bundles/build/, this bundle's own compiled-stylesheet directory (see StylesheetCacheWarmer) - used by FontCssListener here and by ThemeVariablesCssListener, which both rewrite their whole file once per flush. Static and stateless rather than the trait this used to be: a trait shared across bundles is only ever analysed against the users living in the same package, so its callers' own $projectDir read nowhere else looked write-only
class BuildFileWriter
{
    // Written to a temporary file then renamed, so a request reading the file while it's being rewritten never sees a half-written stylesheet - rename() is atomic on the same filesystem
    public static function write(string $projectDir, string $filename, string $contents): void
    {
        $buildDir = $projectDir . '/public/bundles/build';
        if (!is_dir($buildDir) && !@mkdir($buildDir, 0775, true) && !is_dir($buildDir)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory.', $buildDir));
        }

        $path = $buildDir . '/' . $filename;
        $tmpPath = $path . '.' . uniqid('', true) . '.tmp';
        if (false === @file_put_contents($tmpPath, $contents) || !@rename($tmpPath, $path)) {
            throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
        }
    }
}
