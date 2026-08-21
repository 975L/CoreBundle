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

// The sign a live search gives while it is fetching. Two things break it without a trace: "show" instead of "addClass", which a nonced style-src drops on the sites having a CSP and nowhere else (see NoncedStyleSrcTest), and a class the compiled sheet no longer declares, which leaves the toggle doing nothing at all
class SearchBusyTest extends TestCase
{
    private const string TEMPLATE = '/templates/components/Search/Busy.html.twig';

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

    // Live components toggle a sign either way; only one of the two forms survives a policy holding no "unsafe-inline"
    public function testTheSignIsToggledByAClassAndNotByAStyle(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('data-loading="addClass(search-busy--on)"', $template);
        $this->assertStringNotContainsString('data-loading="show', $template);
    }

    // A wait nobody is told about is read by a screen reader as nothing happening
    public function testTheSignIsAnnouncedAndItsSpinnerIsNot(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('role="status"', $template);
        $this->assertStringContainsString('aria-hidden="true"', $template);
    }

    // Hidden and not taken away: the results below it would jump the moment it speaks
    #[DataProvider('stylesheetProvider')]
    public function testTheSignKeepsItsPlaceWhenSilent(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.search-busy\{[^}]*visibility:hidden/',
            $css,
            sprintf('"%s" no longer holds the place of the search sign, so the results below it jump when it speaks.', $file)
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.search-busy\{[^}]*display:none/',
            $css,
            sprintf('"%s" takes the search sign out of the flow, which is what holding its place exists to avoid.', $file)
        );
    }

    // The class the template toggles has to carry the rule, or the toggle does nothing and there is no error to show for it
    #[DataProvider('stylesheetProvider')]
    public function testTheToggledClassCarriesItsRule(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.search-busy--on\{[^}]*visibility:visible/',
            $this->normalize($file),
            sprintf('"%s" no longer declares ".search-busy--on", so the sign stays hidden while the search runs.', $file)
        );
    }

    // Nothing turns for a visitor who asked for less movement, the word alone saying the search is running
    #[DataProvider('stylesheetProvider')]
    public function testTheSpinnerStopsForAVisitorAskingForLessMovement(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion:reduce\)\{\.search-busy__spinner\{animation:none/',
            $this->normalize($file),
            sprintf('"%s" spins the search sign whatever the visitor asked for.', $file)
        );
    }

    private function template(): string
    {
        $path = dirname(__DIR__, 2) . self::TEMPLATE;
        $this->assertFileExists($path, sprintf('"%s" is missing.', self::TEMPLATE));

        return (string) file_get_contents($path);
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
