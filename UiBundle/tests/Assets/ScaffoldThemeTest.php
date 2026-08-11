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

// The scaffolded ui.css is a hand-maintained copy of the token defaults, so it drifts on its own. SiteBundle has the same test over its own scaffold, but it reads this one through vendor/ and skips when absent - which is how three wrong values and three missing tokens shipped here unnoticed. This one needs no vendor
class ScaffoldThemeTest extends TestCase
{
    // Admin-editable from the backoffice, so listing them would hand the palette to this file's editor
    private const ADMIN_EDITABLE = [
        '--primary',
        '--secondary',
        '--background',
        '--text',
        '--font-family-title',
        '--font-family-body',
        '--font-family-accent',
    ];

    // Written by JS on the element itself, never read off :root, so a value here would apply to nothing
    private const RUNTIME_ONLY = [
        '--image-compare-position',
        '--slider-freeflow-vw',
    ];

    // Read, but never off :root either: the "--c975l-" ones are what the backoffice compiles into site-theme.css, and the "--bs-" ones belong to EasyAdmin
    private const NOT_THEMABLE = [
        '--bs-border-color',
        '--bs-primary',
        '--bs-secondary-bg',
        '--bs-secondary-color',
        '--bs-tertiary-bg',
        '--c975l-color-background',
        '--c975l-color-primary',
        '--c975l-color-secondary',
        '--c975l-color-text',
        '--c975l-font-family-accent',
        '--c975l-font-family-body',
        '--c975l-font-family-title',
    ];

    // Set inside the rules of each variant - .section--bg-* for the flats, .card--accent-* for the twelve card hues - so one value in :root would collapse every variant into a single look (the scaffold's own header says as much). A design retunes the tokens those rules point at instead: --section-bg-* for the flats, --block-accent-* for the hues, both of which the scaffold does offer. --card-accent-color and --card-accent-invert are narrower still: only the four light hues (orange, yellow, lime, teal) set them, the eight others falling back on .card-header's own var() defaults. A site retuning one of those eight towards a light hue restates them in its own .card--accent-* rule - see the "Card accents" section of the README. --flip-card-ratio is the same shape one step further: only the eight .flip-card-ratio-* classes set it, one per shape an editor picks per card, and a card left on "free" declares none at all - a value in :root would give every flip card on the site one shape, which is the field's whole point undone. --block-radius and --block-shadow are that same shape again, one per step of the "rounded corners" and "shadow" fields (.block-radius-* / .block-shadow-*): the scale behind them is what a design retunes, and the scaffold does offer it as --block-radius-* / --block-shadow-*
    private const PER_VARIANT = [
        '--section-background',
        '--section-text',
        '--section-text-soft',
        '--section-accent',
        '--section-border',
        '--section-overlay',
        '--card-accent',
        '--card-accent-color',
        '--card-accent-invert',
        '--flip-card-accent',
        '--flip-card-ratio',
        '--block-radius',
        '--block-shadow',
    ];

    // SiteBundle restates these three in its own unlayered :root, which beats this bundle's @layer ui-defaults whatever the load order. The scaffold therefore shows SiteBundle's value, the one a site running it actually gets. Empty this list the day SiteBundle stops redeclaring them
    private const SITE_BUNDLE_OVERRIDES = [
        '--button-background-primary',
        '--button-background-secondary',
        '--button-secondary-color',
    ];

    // A. Every token the bundle declares is offered, so the file stays the single place to look
    public function testScaffoldOffersEveryDeclaredToken(): void
    {
        $missing = array_values(array_diff(
            array_keys($this->compiledRoot()),
            array_keys($this->scaffoldTokens()),
            self::ADMIN_EDITABLE,
            self::RUNTIME_ONLY
        ));

        $this->assertSame([], $missing, sprintf(
            'sass/_tokens.scss declares %s that the scaffolded ui.css never mentions: a design has no way to discover them short of reading the compiled stylesheet.',
            implode(', ', $missing)
        ));
    }

    // B. What the scaffold shows as a default really is the one in force
    public function testScaffoldValuesMatchTheCompiledDefaults(): void
    {
        $compiled = $this->compiledRoot();
        $drifted = [];
        foreach ($this->scaffoldTokens() as $name => $value) {
            if (in_array($name, self::SITE_BUNDLE_OVERRIDES, true)) {
                continue;
            }
            if (isset($compiled[$name]) && $compiled[$name] !== $value) {
                $drifted[] = sprintf('%s (bundle: "%s", scaffold: "%s")', $name, $compiled[$name], $value);
            }
        }

        $this->assertSame([], $drifted, sprintf(
            "The scaffolded ui.css no longer mirrors sass/_tokens.scss:\n- %s",
            implode("\n- ", $drifted)
        ));
    }

    // C. Nothing is ever active, a value here outliving any later change to the bundle's default
    public function testScaffoldShipsEverythingCommentedOut(): void
    {
        $bare = (string) preg_replace('#/\*.*?\*/#s', '', $this->scaffoldSource());

        preg_match_all('/^\s*(--[a-z0-9-]+):/m', $bare, $matches);

        $this->assertSame([], $matches[1], sprintf(
            'The scaffolded ui.css declares %s outside a comment: a fresh site would freeze that value instead of following the bundle.',
            implode(', ', $matches[1])
        ));
    }

    // D. A token read with an inline fallback never reaches :root, so test A above is blind to it - and that is exactly how --contact-details-col-min stayed out of the scaffold for a release
    public function testScaffoldOffersEveryTokenReadWithAnInlineFallback(): void
    {
        $missing = array_values(array_diff(
            $this->tokensReadFromSass(),
            array_keys($this->scaffoldTokens()),
            array_keys($this->compiledRoot()),
            self::ADMIN_EDITABLE,
            self::RUNTIME_ONLY,
            self::NOT_THEMABLE,
            self::PER_VARIANT
        ));

        $this->assertSame([], $missing, sprintf(
            'The sass reads %s with a fallback, so no :root ever carries it and the scaffolded ui.css never offers it: a design has no way to discover it.',
            implode(', ', $missing)
        ));
    }

    /**
     * The tokens the bundle declares, read off the compiled stylesheet rather than the sass so the test
     * sees what a browser sees. They sit in "@layer ui-defaults", which any unlayered :root overrides.
     *
     * @return array<string, string>
     */
    private function compiledRoot(): array
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/styles.css');

        $this->assertSame(1, preg_match('/@layer ui-defaults \{\s*:root \{(.*?)\n  \}/s', $css, $block), 'public/css/styles.css no longer opens with a "@layer ui-defaults" :root block: recompile the sass, or update this test to the new shape.');

        preg_match_all('/(--[a-z0-9-]+):\s*(.+?);/', $block[1], $matches, PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = trim($match[2]);
        }

        return $tokens;
    }

    /**
     * The tokens the scaffold offers, every one of them commented out - which is what test C enforces.
     *
     * @return array<string, string>
     */
    private function scaffoldTokens(): array
    {
        preg_match_all('#/\* (--[a-z0-9-]+):\s*(.+?);\s*\*/#', $this->scaffoldSource(), $matches, PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = trim($match[2]);
        }

        return $tokens;
    }

    /**
     * Every "var(--x, …)" the bundle reads, the fallback being where such a token's default lives.
     *
     * @return string[]
     */
    private function tokensReadFromSass(): array
    {
        $names = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/sass'));

        foreach ($files as $file) {
            if ('scss' !== $file->getExtension()) {
                continue;
            }

            preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*,/', (string) file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        $this->assertNotSame([], $names, 'No "var(--x, …)" found under sass/: the directory moved, and this test would pass blind.');

        return array_keys($names);
    }

    private function scaffoldSource(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/scaffold/assets/styles/themes/ui.css');
    }
}
