<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The wrapper must carry the class and be a <div>, a <p> around Trix's own <p> being split by the browser
class TextHookMarkupTest extends TestCase
{
    public function testTheHookComponentWrapsItsTextInADivCarryingTheClass(): void
    {
        $html = trim($this->render('components/Text/Hook.html.twig', ['text' => '<div>Une accroche</div>']));

        $this->assertStringContainsString('<div class="text-hook text-hook--standalone">', $html);
        $this->assertStringEndsWith('</div>', $html);
        $this->assertStringContainsString('<div>Une accroche</div>', $html);
    }

    // Standing on its own, the block takes the same gutter as every other section-level block, else its
    // bar sits flat against the viewport's left edge. In a column ".flex-columns__col .section-wrap" undoes it
    public function testTheStandaloneHookSitsInsideASectionWrap(): void
    {
        $html = trim($this->render('components/Text/Hook.html.twig', ['text' => 'Une accroche']));

        $this->assertStringStartsWith('<div class="section-wrap">', $html);
    }

    // Raw, not escaped: the editor's own formatting has to survive
    public function testTheHookKeepsItsInlineFormatting(): void
    {
        $html = $this->render('components/Text/Hook.html.twig', ['text' => 'Un <strong>mot</strong> en gras']);

        $this->assertStringContainsString('Un <strong>mot</strong> en gras', $html);
    }

    // An article's own hook shares the base class - same size, same rhythm - but wears its own modifier
    // in place of the standalone one: the block on its own needs a bar to be read against nothing, an
    // article's hook is already framed by the title above it and is marked out by its color instead
    public function testAnArticleHookWearsTheArticleModifierAndNotTheStandaloneOne(): void
    {
        $html = $this->render('components/Article/Article.html.twig', [
            'title' => 'Un titre',
            'hook' => 'Une accroche',
            'content' => 'Le corps du texte',
        ]);

        $this->assertStringContainsString('<div class="text-hook text-hook--article">Une accroche</div>', $html);
        $this->assertStringNotContainsString('text-hook--standalone', $html);
    }

    // An article with no hook renders no wrapper at all: an empty one would still take its own margin
    public function testAnArticleWithoutAHookRendersNoWrapper(): void
    {
        $html = $this->render('components/Article/Article.html.twig', [
            'title' => 'Un titre',
            'content' => 'Le corps du texte',
        ]);

        $this->assertStringNotContainsString('text-hook', $html);
        $this->assertStringContainsString('Le corps du texte', $html);
    }

    // Twig resolves these at compile time, so they must exist even when never reached
    private function render(string $template, array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trix_inline', static fn (?string $value): string => (string) $value, ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => (bool) $value));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (mixed $media): string => ''));

        return $twig->render($template, $context);
    }
}
