<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\CssVariableResolver;
use PHPUnit\Framework\TestCase;

class CssVariableResolverTest extends TestCase
{
    private CssVariableResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CssVariableResolver();
    }

    public function testItReplacesATokenByItsValue(): void
    {
        $css = ':root { --primary: rgb(11, 55, 178); } .btn { background-color: var(--primary); }';

        $this->assertStringContainsString('background-color: rgb(11, 55, 178)', $this->resolver->resolve($css));
    }

    // --primary reads var(--c975l-color-primary, var(--button-background-primary)), so a value is reached
    // through several hops before it is a literal
    public function testItFollowsAChainOfTokens(): void
    {
        $css = ':root { --button-background-primary: rgb(11, 55, 178); --primary: var(--c975l-color-primary, var(--button-background-primary)); } .btn { color: var(--primary); }';

        $this->assertStringContainsString('color: rgb(11, 55, 178)', $this->resolver->resolve($css));
    }

    // The whole point of resolving at render time rather than at compile time: the admin's own palette is
    // written into a later :root block, and has to be what wins
    public function testTheLastRootBlockWins(): void
    {
        $css = ':root { --primary: rgb(11, 55, 178); } :root { --c975l-color-primary: #ff6600; --primary: var(--c975l-color-primary, rgb(11, 55, 178)); } .btn { color: var(--primary); }';

        $this->assertStringContainsString('color: #ff6600', $this->resolver->resolve($css));
    }

    public function testItUsesTheFallbackOfAnUndeclaredToken(): void
    {
        $css = '.btn { border-radius: var(--radius-btn, 5px); }';

        $this->assertStringContainsString('border-radius: 5px', $this->resolver->resolve($css));
    }

    // A fallback holding its own parentheses is what breaks a regex-based substitution: cut at the first
    // comma, the fallback would come out as "color-mix(in srgb" and the rest would be lost. Taking it whole
    // is what lets the mix below be computed at all
    public function testItHandlesAFallbackHoldingParentheses(): void
    {
        $css = '.btn { background: var(--missing, color-mix(in srgb, rgb(1, 2, 3) 50%, white)); }';

        $this->assertStringContainsString('background: rgb(128, 129, 129)', $this->resolver->resolve($css));
    }

    // Undeclared and no fallback: the declaration is invalid whatever happens, so nothing is invented
    public function testItLeavesAnUndeclaredTokenAlone(): void
    {
        $css = '.btn { color: var(--nowhere); }';

        $this->assertStringContainsString('var(--nowhere)', $this->resolver->resolve($css));
    }

    // The footer's border and link hover are mixed out of its background, so a token can still resolve to a
    // color-mix - no better supported than a custom property in a mail client
    public function testItComputesASrgbColorMix(): void
    {
        $css = ':root { --footer-background: rgb(11, 55, 178); } footer { border-top: solid color-mix(in srgb, var(--footer-background) 80%, black) 2px; }';

        // 11*0.8, 55*0.8, 178*0.8 mixed with black
        $this->assertStringContainsString('border-top: solid rgb(9, 44, 142) 2px', $this->resolver->resolve($css));
    }

    // The case that broke the first pattern: an rgb() holds commas of its own
    public function testItMixesColorsHoldingCommas(): void
    {
        $css = '.x { color: color-mix(in srgb, rgb(200, 100, 0) 50%, rgb(0, 0, 0)); }';

        $this->assertStringContainsString('color: rgb(100, 50, 0)', $this->resolver->resolve($css));
    }

    public function testItMixesHexAndNamedColors(): void
    {
        $css = '.x { color: color-mix(in srgb, #fff 50%, black); }';

        $this->assertStringContainsString('color: rgb(128, 128, 128)', $this->resolver->resolve($css));
    }

    // A color space this pass cannot compute is left alone rather than approximated in the wrong one
    public function testItLeavesANonSrgbMixAlone(): void
    {
        $css = '.x { color: color-mix(in oklab, red 50%, blue); }';

        $this->assertStringContainsString('color-mix(in oklab', $this->resolver->resolve($css));
    }

    public function testItDropsTheRootBlocksAndTheirLayer(): void
    {
        $css = '@layer ui-defaults { :root { --primary: red; } } .btn { color: var(--primary); }';
        $resolved = $this->resolver->resolve($css);

        $this->assertStringNotContainsString(':root', $resolved);
        $this->assertStringNotContainsString('@layer', $resolved);
        $this->assertStringContainsString('color: red', $resolved);
    }

    public function testACycleDoesNotHangTheResolver(): void
    {
        $css = ':root { --a: var(--b); --b: var(--a); } .x { color: var(--a); }';

        $this->assertIsString($this->resolver->resolve($css));
    }

    // The real stylesheets, end to end: nothing a mail client cannot resolve may survive the pass
    public function testTheCompiledEmailStylesheetResolvesCompletely(): void
    {
        $path = \dirname(__DIR__, 2) . '/public/css/emails.css';
        $this->assertFileExists($path, 'emails.css is missing, the sass has not been compiled.');

        $resolved = $this->resolver->resolve((string) file_get_contents($path));

        $this->assertStringNotContainsString('var(--', $resolved, 'The compiled email stylesheet still holds an unresolved var() after the pass.');
        $this->assertStringNotContainsString(':root', $resolved);
    }
}
