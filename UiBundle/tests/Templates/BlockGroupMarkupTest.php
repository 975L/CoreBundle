<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Form\Block\BlockGroupType;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// Both values come from stored block data, so the group is rendered rather than read as text - and every class it does build has to exist in the compiled stylesheets, a group being nothing but the layout it gives its slots
class BlockGroupMarkupTest extends TestCase
{
    public function testTheGroupRendersEveryOneOfItsSlots(): void
    {
        $html = $this->render([]);

        $this->assertSame(2, substr_count($html, '<span>slot</span>'));
    }

    // "row" and "center" are the group's own defaults and write no class of their own
    public function testTheDefaultsWriteNoModifier(): void
    {
        $this->assertStringContainsString('class="blocks-group"', $this->render([]));
        $this->assertStringContainsString('class="blocks-group"', $this->render(['direction' => 'row', 'justify' => 'center']));
    }

    public function testEachStoredValueBuildsItsOwnModifier(): void
    {
        $this->assertStringContainsString('class="blocks-group blocks-group--column"', $this->render(['direction' => 'column']));
        $this->assertStringContainsString('class="blocks-group blocks-group--between"', $this->render(['justify' => 'between']));
        $this->assertStringContainsString('class="blocks-group blocks-group--column blocks-group--start"', $this->render(['direction' => 'column', 'justify' => 'start']));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function unknownValueProvider(): array
    {
        return [
            'unknown layout' => [['direction' => 'whatever']],
            'unknown alignment' => [['justify' => 'whatever']],
        ];
    }

    // Matched, never interpolated: a value no modifier covers falls back to the defaults instead of naming a class no stylesheet carries
    #[\PHPUnit\Framework\Attributes\DataProvider('unknownValueProvider')]
    public function testAnUnknownValueBuildsNoClassOfItsOwn(array $context): void
    {
        $this->assertStringContainsString('class="blocks-group"', $this->render($context));
        $this->assertStringNotContainsString('whatever', $this->render($context));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // A modifier the form offers but no stylesheet carries silently renders as the default layout
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEachStylesheetCarriesTheGroupAndEveryModifierItsFormOffers(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertStringContainsString('.blocks-group{display:flex', $css);
        $this->assertStringContainsString('.blocks-group--column{', $css, sprintf('"%s" carries no rule for the stacked layout.', $file));

        // "center" is the group's own default and writes no class, hence no rule of its own to find here
        foreach (array_diff(BlockGroupType::JUSTIFICATIONS, ['center']) as $justify) {
            $this->assertStringContainsString('.blocks-group--' . $justify . '{', $css, sprintf('"%s" carries no rule for the "%s" alignment.', $file, $justify));
        }
    }

    // render_block() is the runtime's own, stubbed here down to what the group does with it: render each slot in order
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFunction(new TwigFunction('render_block', static fn (Block $slot): string => '<span>slot</span>', ['is_safe' => ['html']]));

        $block = new Block();
        $block->addSlot(new Block());
        $block->addSlot(new Block());

        return $twig->render('blocks/BlockGroup.html.twig', $context + ['block' => $block]);
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function stylesheet(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    }
}
