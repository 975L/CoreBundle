<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

// The [client] options file mysql and mysqldump read the site-backup-db-* credentials from, so they never appear in the process list - shared by c975l:config:backup and c975l:config:export-tables
final class MySqlOptionsFile
{
    // Writes the file, readable by its owner alone, and returns its path: the caller removes it once done
    public static function write(ConfigServiceInterface $configService, string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        chmod($path, 0600);
        file_put_contents($path, self::content(
            (string) $configService->get('site-backup-db-host'),
            (string) $configService->get('site-backup-db-user'),
            (string) $configService->get('site-backup-db-password'),
        ));

        return $path;
    }

    // Each value in double quotes, its backslashes and quotes escaped: unquoted, a "#" opens a comment and a "\" an escape, a password holding either being read back as something else
    public static function content(string $host, string $user, string $password): string
    {
        return "[client]\nhost=" . self::quote($host) . "\nuser=" . self::quote($user) . "\npassword=" . self::quote($password) . "\n";
    }

    // One value as the option file parser reads it back unchanged
    private static function quote(string $value): string
    {
        return '"' . addcslashes($value, '\\"') . '"';
    }
}
