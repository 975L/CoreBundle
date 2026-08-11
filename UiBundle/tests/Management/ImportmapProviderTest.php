<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Management\ImportmapProvider;
use PHPUnit\Framework\TestCase;

class ImportmapProviderTest extends TestCase
{
    public function testGetAdminImportmapEntriesReturnsControllersAdminEntrypoint(): void
    {
        $entries = new ImportmapProvider()->getAdminImportmapEntries();

        $this->assertSame([
            '@c975l/ui-bundle/controllers-admin.js' => [
                'path' => 'assets/controllers-admin.js',
                'entrypoint' => true,
            ],
            '@c975l/ui-bundle/pointer-sort.js' => [
                'path' => 'assets/js/pointer-sort.js',
            ],
        ], $entries);
    }

    // The drag gesture is imported by name from another bundle, so it needs an entry of its own - but not an entrypoint: nothing ever loads it as a script tag
    public function testPointerSortIsDeclaredWithoutBeingAnEntrypoint(): void
    {
        $entry = new ImportmapProvider()->getAdminImportmapEntries()['@c975l/ui-bundle/pointer-sort.js'] ?? null;

        $this->assertNotNull($entry, 'The drag gesture module is no longer importable from outside UiBundle.');
        $this->assertArrayNotHasKey('entrypoint', $entry, 'The drag gesture module is declared as an entrypoint, which would have the dashboard load it as a script of its own.');
        $this->assertFileExists(dirname(__DIR__, 2) . '/' . $entry['path'], 'The declared path points at no file, so the entry resolves to nothing in the browser.');
    }

    public function testGetImportmapEntriesReturnsControllersEntrypoint(): void
    {
        $entries = new ImportmapProvider()->getImportmapEntries();

        $this->assertSame([
            '@c975l/ui-bundle/controllers.js' => [
                'path' => 'assets/controllers.js',
                'entrypoint' => true,
            ],
        ], $entries);
    }
}
