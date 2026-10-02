<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// The front's per-item pencil (see edit-pencils.js) sends "focusItem" next to "focusBlock": block-focus.js has to read it and find the item by the name its fields carry
class BlockFocusItemTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/block-focus.js';

    // The param name is the contract shared with edit-pencils.js
    public function testItReadsTheFocusItemQueryParam(): void
    {
        $this->assertStringContainsString("get('focusItem')", $this->read(self::CONTROLLER_JS));
        $this->assertStringContainsString('focusItem=', $this->read('assets/js/edit-pencils.js'));
    }

    // "rows.3" is looked up as the "[rows][3][" its fields are named with, then its own collection entry
    public function testTheItemIsLookedUpByItsFieldNames(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString("split('.').map(part => `[\${part}]`).join('')}[`", $controller);
        $this->assertStringContainsString("closest('.field-collection-item')", $controller);
    }

    // The comparison table marks each row by its stored key, which stays the one its fields are named with once a row is removed
    public function testTheComparisonTableMarksItsRows(): void
    {
        $template = $this->read('templates/components/Comparison/Table.html.twig');

        $this->assertStringContainsString('{% for key, row in rows %}', $template);
        $this->assertStringContainsString('data-edit-item="rows.{{ key }}"', $template);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
