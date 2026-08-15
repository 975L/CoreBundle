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

// A card's size is a count to the row: each "card--<step>" class does nothing but point the card at its own --card-width-* token (see CardMeasureTest for what those tokens compute), and the flip card reads the very same ones - a row mixing the two kinds holds together only if a flip card sized "big" is as wide as a card sized "big".
class CardSizeTest extends TestCase
{
    // Each step, and the token both kinds measure it off
    private const array STEPS = [
        'compact' => '--card-width-compact',
        'big' => '--card-width-big',
    ];

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

    // A width written down again here is a number computed against the default page, which is what dropped a card off the row on every site framing its content tighter
    #[DataProvider('stylesheetProvider')]
    public function testBothKindsMeasureEveryStepOffTheSameToken(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::STEPS as $step => $token) {
            foreach (['card', 'flip-card'] as $kind) {
                $this->assertMatchesRegularExpression(
                    sprintf('/\.%s--%s\{[^}]*width:var\(%s\)/', preg_quote($kind, '/'), $step, preg_quote($token, '/')),
                    $css,
                    sprintf('".%s--%s" no longer reads "%s" in "%s", so a row mixing the two kinds is no longer even.', $kind, $step, $token, $file)
                );
            }
        }
    }

    // The title follows the width on the plain card: 19px reads large across 190px and small across 570px, and on a big card the two titles are what the visitor compares. The flip card's is left alone, drawn from --flip-card-title-size
    #[DataProvider('stylesheetProvider')]
    public function testTheCardTitleStepsWithItsWidth(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (array_keys(self::STEPS) as $step) {
            $this->assertMatchesRegularExpression(
                sprintf('/\.card--%s h2\.card-header\{[^}]*font-size:var\(--card-title-size-%s\)/', $step, $step),
                $css,
                sprintf('A ".card--%s" title no longer steps with its width in "%s".', $step, $file)
            );
        }
    }

    // Read against the closed list rather than interpolated, same rule as the accent above it: the value comes from stored block data and must never write a class name of its own
    public function testBothAdaptersMatchTheStoredValueAgainstTheClosedList(): void
    {
        foreach (['Card' => 'card', 'FlipCard' => 'flip-card'] as $template => $prefix) {
            $adapter = $this->adapter($template);

            $this->assertStringContainsString(sprintf("in ['compact', 'big'] ? '%s--' ~ size", $prefix), $adapter, sprintf('"%s.html.twig" no longer matches the stored size against the two named steps.', $template));
            $this->assertStringContainsString('sizeClass', $adapter, sprintf('"%s.html.twig" no longer writes the size class it computed.', $template));
        }
    }

    // Comments out, then every space a minifier would drop - the space of a descendant combinator kept, the title rules here being read on one
    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s+/', ' ', $css);

        return (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    }

    private function adapter(string $template): string
    {
        $path = \dirname(__DIR__, 2) . '/templates/blocks/' . $template . '.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
