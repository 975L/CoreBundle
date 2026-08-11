<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\UiBackupPathProvider;
use PHPUnit\Framework\TestCase;

class UiBackupPathProviderTest extends TestCase
{
    // The list used to be written out by hand, and the two watermark roles added later were simply not in it - nothing under public/ being mirrored, they were backed up nowhere at all
    public function testEverySingletonRoleIsDeclared(): void
    {
        $paths = array_map(fn (BackupPath $path) => $path->path, new UiBackupPathProvider()->getBackupPaths());

        foreach (Media::getSingletonRoles() as $role) {
            $declared = array_filter($paths, fn (string $path) => str_starts_with($path, 'public/' . $role . '.'));

            $this->assertNotEmpty($declared, sprintf('Role "%s" is backed up nowhere.', $role));
        }
    }

    // A role with a fixed icon spec only ever lands in that one format, the others keep whatever they were uploaded as - an SVG logo used to fall outside the list along with the webp one it was assumed to be
    public function testTheDeclaredExtensionsFollowEachRolesOwnFormat(): void
    {
        $paths = array_map(fn (BackupPath $path) => $path->path, new UiBackupPathProvider()->getBackupPaths());

        $this->assertContains('public/favicon.ico', $paths);
        $this->assertContains('public/apple-touch-icon.png', $paths);
        $this->assertContains('public/logo.webp', $paths);
        $this->assertContains('public/logo.svg', $paths);
        $this->assertNotContains('public/favicon.webp', $paths);
    }

    // The uploads themselves are mirrored, not archived: a folder that grows without bound has no business being carried on every run
    public function testTheUploadFoldersAreMirrored(): void
    {
        $modes = [];
        foreach (new UiBackupPathProvider()->getBackupPaths() as $path) {
            $modes[$path->path] = $path->mode;
        }

        $this->assertSame(BackupPath::MODE_MIRROR, $modes['public/medias/site']);
        $this->assertSame(BackupPath::MODE_MIRROR, $modes['public/medias/fonts']);
        $this->assertSame(BackupPath::MODE_ARCHIVE, $modes['public/favicon.ico']);
    }
}
