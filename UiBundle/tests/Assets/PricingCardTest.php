<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The pricing card grows the price it holds through a token and not through PaymentBundle's class: every other price on a site keeps its size, which is what the sites already in production are drawn against
class PricingCardTest extends TestCase
{
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

    // The size is set on the card, for the price inside it to read, never on ".price" itself
    #[DataProvider('stylesheetProvider')]
    public function testThePriceGrowsThroughTheToken(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression('/\.card--pricing\{[^}]*--price-size:var\(--card-pricing-price-size,2\.25rem\)/', $css, sprintf('"%s" no longer grows the price of a pricing card through --price-size.', $file));
        $this->assertDoesNotMatchRegularExpression('/(^|[},])\.price\{/', $css, sprintf('"%s" styles ".price" itself, which would resize every price of the sites.', $file));
    }

    // The tick is escaped in the sheet, written raw it would show as a diamond without a @charset
    #[DataProvider('stylesheetProvider')]
    public function testTheFeaturesAreTickedWithAnEscapedGlyph(string $file): void
    {
        $this->assertMatchesRegularExpression('/\.card-features li::before\{[^}]*content:"\\\\2713"/', $this->normalize($file, false), sprintf('"%s" does not tick the lines of ".card-features" with an escaped glyph.', $file));
    }

    // The title set beside a mention sits in a span the theme's "*" reaches, so it restates the heading's font
    #[DataProvider('stylesheetProvider')]
    public function testATitleBesideAMentionKeepsTheTitleFont(string $file): void
    {
        $this->assertMatchesRegularExpression('/\.card-header__title,\.card-header__aside\{font-family:inherit/', $this->normalize($file), sprintf('"%s" lets the title beside a mention fall back on the body font.', $file));
    }

    // Without the band the header's white icon would sit on the white card, so it reads as a secondary button's icon
    #[DataProvider('stylesheetProvider')]
    public function testTheHeaderIconFollowsASecondaryButton(string $file): void
    {
        $this->assertMatchesRegularExpression('/\.card--pricing \.card-header \.icon\{filter:brightness\(0\) invert\(var\(--button-secondary-icon-invert,0\)\)/', $this->normalize($file, false), sprintf('"%s" leaves the icon of a pricing card white on the white card.', $file));
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file, bool $collapse = true): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return $collapse ? (string) preg_replace('/\s+/', '', $css) : (string) preg_replace(['/\s+/', '/\s*([{};:,])\s*/'], [' ', '$1'], $css);
    }
}
