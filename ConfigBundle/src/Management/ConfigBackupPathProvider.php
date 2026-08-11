<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// What this bundle can honestly claim, and nothing more. Declaring public/medias from here would have covered every other bundle's uploads - the very habit the provider interface exists to break, and the reason a site with an unusual layout used to be backed up wrongly rather than differently. Each bundle declares its own folders
class ConfigBackupPathProvider implements BackupPathProviderInterface
{
    public function getBackupPaths(): array
    {
        return [
            // Neither in git nor in the database, and the one file whose absence stops a restored server from starting at all: APP_SECRET, the mailer DSN, the database url. Small enough to be archived whole on every run, so its history is kept too
            new BackupPath('.env.local', BackupPath::MODE_ARCHIVE),
        ];
    }
}
