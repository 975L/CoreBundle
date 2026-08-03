<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class BlockAnchorSluggerTest extends TestCase
{
    private function createSlugger(): BlockAnchorSlugger
    {
        return new BlockAnchorSlugger(new AsciiSlugger());
    }

    public function testSlugifyUsesTheExplicitAnchorWhenGiven(): void
    {
        $this->assertSame('services', $this->createSlugger()->slugify('Services', 'A much longer section title'));
    }

    public function testSlugifyFallsBackToTheTitleWhenAnchorIsEmpty(): void
    {
        $this->assertSame('des-services-taille-sur-mesure', $this->createSlugger()->slugify('', 'Des services taillé sur mesure'));
        $this->assertSame('des-services-taille-sur-mesure', $this->createSlugger()->slugify(null, 'Des services taillé sur mesure'));
    }

    public function testSlugifyReturnsNullWhenBothAnchorAndTitleAreEmpty(): void
    {
        $this->assertNull($this->createSlugger()->slugify(null, null));
        $this->assertNull($this->createSlugger()->slugify('', ''));
        $this->assertNull($this->createSlugger()->slugify('   ', '  '));
    }

    // The title fallback may come from a TrixEditorType field (e.g. HeroType) - its inline markup must not leak into the slug as stray words
    public function testSlugifyStripsHtmlTagsFromTheTitleFallback(): void
    {
        $this->assertSame('votre-projet', $this->createSlugger()->slugify(null, 'Votre <em>projet</em>'));
    }
}
