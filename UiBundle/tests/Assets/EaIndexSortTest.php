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

// Reordering an EasyAdmin index by dragging its rows is a browser gesture, so nothing here proves it works - what is locked is the contract the CRUD controllers of the other bundles write their row attributes against (see SiteBundle's collection_item_crud_index.html.twig and ShopBundle's product_crud_index.html.twig), which drifts silently, the repository having no browser to run the controller in
class EaIndexSortTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/ea-index-sort.js';
    private const string ADMIN_BARREL = 'assets/controllers-admin.js';
    private const string IDENTIFIER = 'eaIndexSort';

    public function testTheControllerIsRegisteredInTheAdminBarrel(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);

        $this->assertStringContainsString("import EaIndexSortController from './js/ea-index-sort.js';", $barrel);
        $this->assertStringContainsString(sprintf("app.register('%s', EaIndexSortController);", self::IDENTIFIER), $barrel);
    }

    // Registering it is not enough: an EasyAdmin index never writes data-controller, the barrel mounts it on <body> itself
    public function testTheIdentifierIsMountedOnBody(): void
    {
        $this->assertMatchesRegularExpression(
            sprintf("/document\.body\.setAttribute\(\s*'data-controller',\s*\[[^\]]*'%s'[^\]]*\]/s", self::IDENTIFIER),
            $this->read(self::ADMIN_BARREL),
            sprintf('"%s" is registered but never added to the <body> mount, so it never connects.', self::IDENTIFIER)
        );
    }

    // The three attributes a CRUD opts in with, plus the position column the grip is prepended to: an index declaring none of them is left alone
    public function testItArmsTheRowsDeclaringTheReorderAttributes(): void
    {
        $js = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('tr[data-id][data-reorder-url]', $js);
        $this->assertStringContainsString('dataset.reorderToken', $js);
        $this->assertStringContainsString('dataset.reorderGroup', $js);
        $this->assertStringContainsString('td[data-column="position"]', $js);
    }

    // The payload the reorder actions read, and the answer they write back into the cells
    public function testItPostsTheNewOrderAndAppliesTheAnsweredPositions(): void
    {
        $js = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('JSON.stringify({ group:', $js);
        $this->assertStringContainsString('ids,', $js);
        $this->assertStringContainsString('_token:', $js);
        $this->assertStringContainsString('payload?.positions', $js);
    }

    // Built on the shared gesture layer, not on the native drag API no touch browser can be counted on to emit (see PointerSortTest)
    public function testTheDragRunsOnTheSharedPointerGesture(): void
    {
        $js = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString("import { addSortGesture } from './pointer-sort.js';", $js);
        $this->assertDoesNotMatchRegularExpression('/addEventListener\([\'"]drag/', $js, 'The native drag API is back, and touch browsers do not fire it.');
    }

    // The same grip as a collection row's handle, declared once rather than in each sortable
    public function testTheGripComesFromTheSharedIcon(): void
    {
        foreach ([self::CONTROLLER_JS, 'assets/js/ea-sortable.js'] as $file) {
            $this->assertStringContainsString("from './sort-icon.js';", str_replace('"', "'", $this->read($file)), sprintf('%s no longer reads the shared move icon.', $file));
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
