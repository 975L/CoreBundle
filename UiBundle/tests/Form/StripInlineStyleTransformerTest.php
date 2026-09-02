<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\StripInlineStyleTransformer;
use PHPUnit\Framework\TestCase;

class StripInlineStyleTransformerTest extends TestCase
{
    public function testTransformCleansStoredContentBeforeTheEditorSeesIt(): void
    {
        $transformer = new StripInlineStyleTransformer();

        $this->assertSame('<div>a</div>', $transformer->transform('<div style="color: red">a</div>'));
    }

    public function testReverseTransformDropsStyleAttributes(): void
    {
        $transformer = new StripInlineStyleTransformer();

        $html = '<div style="color: rgb(33, 37, 41);">Texte <em style="font-weight: 700">gras</em></div>';

        $this->assertSame('<div>Texte <em>gras</em></div>', $transformer->reverseTransform($html));
    }

    public function testReverseTransformKeepsEverythingElse(): void
    {
        $transformer = new StripInlineStyleTransformer();

        $html = '<div class="text-center" style="text-align: center"><a href="/page">Lien</a> &amp; suite</div><div><br></div>';

        $this->assertSame('<div class="text-center"><a href="/page">Lien</a> &amp; suite</div><div><br></div>', $transformer->reverseTransform($html));
    }

    public function testReverseTransformDoesNotBreakTextHoldingTheWordsStyleEquals(): void
    {
        $transformer = new StripInlineStyleTransformer();

        $html = '<div>Le mot style= reste tel quel</div>';

        $this->assertSame($html, $transformer->reverseTransform($html));
    }

    public function testReverseTransformPassesNonStringsThrough(): void
    {
        $transformer = new StripInlineStyleTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }
}
