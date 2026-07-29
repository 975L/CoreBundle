<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Form\Block\FlexColumnType;
use PHPUnit\Framework\TestCase;

// Locks every offered width to a compiled rule that subtracts its own share of the row's gutter
class FlexColumnWidthTest extends TestCase
{
    // $page-section-bp-md, as it reads once normalized
    private const BREAKPOINT = '@media(min-width:861px)';

    // One formula for all twelve, reading the unit its own class declares
    private const FORMULA = 'calc(100% * var(--flex-columns-span) / 12 - var(--flex-columns-gap) * (12 - var(--flex-columns-span)) / 12)';

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

    // The form and the stylesheets are written apart from each other: nothing but this ties them together
    public function testTheFormOffersExactlyTheTwelveUnitsTheStylesheetsSize(): void
    {
        $this->assertSame(array_map('strval', range(1, 12)), FlexColumnType::WIDTHS);
    }

    // A unit the form offers and no rule sizes is a setting that silently does nothing
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryOfferedUnitDeclaresItsOwnSpanUnderTheWideScreenPalier(string $file): void
    {
        $rules = $this->wideScreenRules($file);

        foreach (FlexColumnType::WIDTHS as $span) {
            $this->assertStringContainsString(
                $this->unspaced('--flex-columns-span: ' . $span),
                $this->unspaced($this->declarationsOf($rules, '.flex-columns__col--' . $span, $file)),
                sprintf('".flex-columns__col--%s" does not declare its own span in "%s".', $span, $file)
            );
        }
    }

    // Each unit hands back the (12 - n)/12 of the gutter it doesn't need, so any set summing to 12 fits
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryUnitIsSizedByTheOneGutterCompensatingFormula(string $file): void
    {
        $rules = $this->wideScreenRules($file);

        foreach (FlexColumnType::WIDTHS as $span) {
            $this->assertStringContainsString(
                $this->unspaced(self::FORMULA),
                $this->unspaced($this->declarationsOf($rules, '.flex-columns__col--' . $span, $file)),
                sprintf('".flex-columns__col--%s" is not sized by the shared formula in "%s".', $span, $file)
            );
        }
    }

    // The comparison is made blind to the spaces a minifier drops around calc()'s operators
    private function unspaced(string $declarations): string
    {
        return str_replace(' ', '', $declarations);
    }

    // The row must space its columns with that very token, else every width is off by it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheRowDeclaresTheGutterAsATokenAndSpacesItselfWithIt(string $file): void
    {
        $declarations = $this->declarationsOf($this->normalize($file), '.flex-columns', $file);

        $this->assertStringContainsString('--flex-columns-gap: 32px', $declarations, sprintf('".flex-columns" does not declare the gutter token in "%s".', $file));
        $this->assertStringContainsString('gap: var(--flex-columns-gap)', $declarations, sprintf('".flex-columns" does not space itself with its own gutter token in "%s".', $file));
    }

    // No width class outside the media query: a quarter of a phone screen is unreadable
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoWidthIsSizedOutsideTheWideScreenPalier(string $file): void
    {
        $css = $this->normalize($file);
        $rules = $this->wideScreenRules($file);

        $this->assertSame(
            substr_count($rules, '.flex-columns__col--'),
            substr_count($css, '.flex-columns__col--'),
            sprintf('A ".flex-columns__col--" rule sits outside the %s palier in "%s".', self::BREAKPOINT, $file)
        );
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
        $css = (string) preg_replace('/\s+/', ' ', $css);

        // The one space a minifier drops that the collapsing above leaves behind
        return (string) preg_replace('/@media\s+/', '@media', $css);
    }

    // The body of the wide-screen media query, brace-matched so a nested rule can't cut it short
    private function wideScreenRules(string $file): string
    {
        $css = $this->normalize($file);
        $start = strpos($css, self::BREAKPOINT . '{');
        $this->assertNotFalse($start, sprintf('"%s" has no "%s" palier at all.', $file, self::BREAKPOINT));

        $depth = 0;
        $length = strlen($css);
        for ($end = $start + strlen(self::BREAKPOINT); $end < $length; $end++) {
            $depth += '{' === $css[$end] ? 1 : ('}' === $css[$end] ? -1 : 0);
            if (0 === $depth) {
                break;
            }
        }

        return substr($css, $start, $end - $start);
    }

    // The body of the rule holding $selector, spaced back out the way the sass writes it
    private function declarationsOf(string $css, string $selector, string $file): string
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!in_array($selector, explode(',', trim($match[1])), true)) {
                continue;
            }

            return str_replace([':', ','], [': ', ', '], $match[2]);
        }

        $this->fail(sprintf('"%s" has no "%s" rule at all.', $file, $selector));
    }
}
