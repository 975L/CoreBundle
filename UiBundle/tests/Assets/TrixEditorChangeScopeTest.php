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

// EasyAdmin answers a trix-change on the document by rebuilding its unsaved-changes tracker, once per editor of the page, adding two listeners to every field of the form and keeping all the ones it added before: an insertion measured 142ms, then 228, then 294 on a page holding 31 editors. Locked statically, the repository having no browser to run the controller in
class TrixEditorChangeScopeTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/trix-editor.js');
    }

    public function testTheChangeEventIsKeptInsideItsOwnEditor(): void
    {
        $this->assertMatchesRegularExpression(
            '/addEventListener\(\'trix-change\', \(event\) => \{\s*event\.stopPropagation\(\);/',
            $this->script(),
            'trix-change reaches the document again, so EasyAdmin rebuilds its unsaved-changes tracker on every keystroke of every editor.'
        );
    }

    public function testARequiredFieldStillClearsItsValidityOnceFilled(): void
    {
        $this->assertMatchesRegularExpression(
            '/eaTrixIsRequired.*?setCustomValidity\(\'\' === textarea\.value \? \'invalid\' : \'\'\)/s',
            $this->script(),
            'Nothing re-runs the validity of a required editor now that EasyAdmin no longer sees its changes, so a filled field stays barred from submission.'
        );
    }

    public function testTheScopeIsInstalledOnEveryEditorItBuilds(): void
    {
        $this->assertMatchesRegularExpression(
            '/initChangeScope\(editor, textarea\);/',
            $this->script(),
            'initTrixEditors() builds an editor without scoping its change event, so that one keeps paying the whole cost.'
        );
    }
}
