<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Twig\CollectionExtension;
use c975L\UiBundle\Twig\CollectionRuntime;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

// The block writes its own section head around whatever the runtime hands back (see CollectionRuntimeTest for the entry itself), and what it does with nothing at all is the whole point of that head being conditional
class CollectionEntryMarkupTest extends TestCase
{
    public function testTheEntryIsWrappedInASectionCarryingItsHead(): void
    {
        $html = $this->render(['source' => 'guild.albums', 'eyebrow' => 'Le dernier', 'title' => 'Album à la une'], '<div class="card">Entry</div>');

        $this->assertStringContainsString('<section class="collection-entry">', $html);
        $this->assertStringContainsString('<p class="section-eyebrow">Le dernier</p>', $html);
        $this->assertStringContainsString('<h2 class="section-title">Album à la une</h2>', $html);
        $this->assertStringContainsString('<div class="card">Entry</div>', $html);
    }

    // A headingless <section> is invalid HTML, so a block given no title is a plain <div> - eyebrow or not, an eyebrow being no heading
    public function testABlockWithNoTitleIsADivRatherThanASection(): void
    {
        $html = $this->render(['source' => 'guild.albums', 'eyebrow' => 'Le dernier'], '<div class="card">Entry</div>');

        $this->assertStringContainsString('<div class="collection-entry">', $html);
        $this->assertStringNotContainsString('<section', $html);
        $this->assertStringContainsString('<p class="section-eyebrow">Le dernier</p>', $html);
    }

    // An empty source, or a slug matching none: the head would otherwise stand over a hole in the page
    public function testNothingIsRenderedAtAllWhenTheEntryAnswersNothing(): void
    {
        $this->assertSame('', trim($this->render(['source' => 'guild.albums', 'title' => 'Album à la une'], '')));
        $this->assertSame('', trim($this->render(['source' => 'guild.albums', 'title' => 'Album à la une'], "\n  ")));
    }

    public function testTheAnchorIsWrittenOnlyWhenTheBlockCarriesOne(): void
    {
        $entry = '<div class="card">Entry</div>';

        $this->assertStringContainsString('id="albums"', $this->render(['source' => 'guild.albums', 'anchor_id' => 'albums'], $entry));
        $this->assertStringNotContainsString(' id=', $this->render(['source' => 'guild.albums'], $entry));
    }

    // The four fields the block stores are what reaches the runtime, the entry itself being none of this template's business
    public function testTheBlocksOwnFieldsArePassedToTheRuntime(): void
    {
        $runtime = $this->createMock(CollectionRuntime::class);
        $runtime->expects($this->once())
            ->method('renderEntry')
            ->with('guild.albums', 'slug', 'nordkapp', 'albums')
            ->willReturn('<div class="card">Entry</div>');

        $this->render(['source' => 'guild.albums', 'pick' => 'slug', 'slug' => 'nordkapp', 'detailPage' => 'albums'], null, $runtime);
    }

    private function render(array $context, ?string $entry = '', ?CollectionRuntime $runtime = null): string
    {
        if (null === $runtime) {
            $stub = $this->createStub(CollectionRuntime::class);
            $stub->method('renderEntry')->willReturn((string) $entry);
            $runtime = $stub;
        }

        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addExtension(new CollectionExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            CollectionRuntime::class => static fn (): CollectionRuntime => $runtime,
        ]));

        return $twig->render('blocks/CollectionEntry.html.twig', $context);
    }
}
