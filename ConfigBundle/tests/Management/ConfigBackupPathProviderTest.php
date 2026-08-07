<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\ConfigBackupPathProvider;
use PHPUnit\Framework\TestCase;

class ConfigBackupPathProviderTest extends TestCase
{
    // Neither in git nor in the database, and the one file whose absence stops a restored server from starting at all
    public function testItDeclaresTheEnvironmentFile(): void
    {
        $paths = $this->paths();

        $this->assertArrayHasKey('.env.local', $paths);
    }

    // Small enough to be carried whole on every run, which is what keeps a history of it rather than only its last state
    public function testTheEnvironmentFileIsArchivedRatherThanMirrored(): void
    {
        $this->assertSame(BackupPath::MODE_ARCHIVE, $this->paths()['.env.local']);
    }

    // Declaring them from here would cover every other bundle's uploads under this bundle's name - the very habit the provider interface exists to break
    public function testItClaimsNoUploadFolderOfAnotherBundle(): void
    {
        foreach (array_keys($this->paths()) as $path) {
            $this->assertStringNotContainsString('medias', $path, sprintf('"%s" claims uploads this bundle does not write.', $path));
        }
    }

    /**
     * @return array<string, string> the declared paths, keyed by path
     */
    private function paths(): array
    {
        $paths = [];
        foreach ((new ConfigBackupPathProvider())->getBackupPaths() as $path) {
            $paths[$path->path] = $path->mode;
        }

        return $paths;
    }
}
