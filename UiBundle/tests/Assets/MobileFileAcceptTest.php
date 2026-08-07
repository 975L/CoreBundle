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

// The module is what lets an admin editing a block from a phone reach kDrive/Nextcloud/"Files" instead of the gallery alone, and the repository has no browser to run it in - what can still be checked is that it is loaded at all, and that the three conditions keeping it harmless (mouse untouched, media-only lists only, delegated so late-arriving inputs are covered) are still written in it
class MobileFileAcceptTest extends TestCase
{
    private const MODULE_JS = 'assets/js/mobile-file-accept.js';
    private const ADMIN_BARREL = 'assets/controllers-admin.js';

    // Not a Stimulus controller: it is imported for its side effect only, so a missing import is the whole feature missing
    public function testTheModuleIsImportedByTheAdminBarrel(): void
    {
        $this->assertStringContainsString("import './js/mobile-file-accept.js';", $this->read(self::ADMIN_BARREL));
    }

    // Block media rows come from a collection prototype and whole block forms arrive over AJAX (see BlockFormController) - an input bound at load time would miss most of them
    public function testItListensOnTheDocumentBeforeThePickerOpens(): void
    {
        $this->assertStringContainsString("document.addEventListener('pointerdown'", $this->read(self::MODULE_JS));
    }

    // A desktop admin keeps their images-only file dialog: only Android's photo picker has the problem, and it is only ever reached by touch
    public function testItLeavesTheMouseAlone(): void
    {
        $this->assertStringContainsString("if ('mouse' === event.pointerType) return;", $this->read(self::MODULE_JS));
    }

    // A PDF/font/zip input already opens the document picker, so its filter is kept - only a list holding nothing but image/video types triggers the gallery-only picker
    public function testOnlyMediaOnlyAcceptListsAreStripped(): void
    {
        $module = $this->read(self::MODULE_JS);

        $this->assertStringContainsString('/^(image|video)\//', $module);
        $this->assertStringContainsString('types.every(', $module);
        $this->assertStringContainsString("removeAttribute('accept')", $module);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
