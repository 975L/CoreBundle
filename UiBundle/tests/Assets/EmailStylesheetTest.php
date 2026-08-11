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

// An email carries no <link>: its CSS travels inside the message, sourced and inlined by the sending bundle's own layout. Nothing there falls back to a page stylesheet, so a token this file reads without declaring is simply an invalid declaration the client throws away - which is how the footer briefly lost every one of its --footer-* rules when the base moved here. The page has TokenDefaultsTest for the same reason; this is its email counterpart, and it only checks what UiBundle ships on its own.
class EmailStylesheetTest extends TestCase
{
    public function testEveryTokenTheRulesReadIsDeclared(): void
    {
        $css = $this->compiled();

        $declared = $this->declaredTokens($css);
        $read = $this->tokensReadByRules($css);

        $missing = array_values(array_diff($read, $declared));

        $this->assertSame([], $missing, sprintf(
            'The compiled email stylesheet reads %s without declaring it - inlined into a message, those declarations are dropped.',
            implode(', ', $missing)
        ));
    }

    // A mail client resolves no custom property of its own and applies no page stylesheet, so the :root block has to travel with the rules that read it
    public function testTheStylesheetCarriesItsOwnRootBlock(): void
    {
        $this->assertStringContainsString(':root', $this->compiled(), 'The compiled email stylesheet declares no :root, so every token it reads resolves to nothing.');
    }

    // The background is plain white (see emails/_base.scss), and CssVariableResolver resolves every var() with the site's own theme right before inlining: a "color: var(--text)" on the blanket rule would send white on white to any site with a dark palette, which is why that one color is fixed too
    public function testTheBlanketTextColorIsFixedRatherThanThemed(): void
    {
        // The blanket rule alone, not the "*, *::before, *::after" one setting box-sizing
        preg_match('/^\*\s*\{([^}]*)\}/m', $this->compiled(), $matches);

        $this->assertNotEmpty($matches, 'The compiled email stylesheet carries no blanket "*" rule, the test itself is broken.');
        $this->assertMatchesRegularExpression(
            '/color:\s*#[0-9a-f]{3,6}\s*;/i',
            $matches[1],
            'The email blanket rule reads a themed color: against the fixed white background, a dark palette sends an unreadable message.'
        );
    }

    // The page layer has no place here: an email is laid out in tables, and a client that ignores these collapses the layout rather than degrading it
    public function testTheStylesheetAvoidsWhatMailClientsDrop(): void
    {
        $css = $this->compiled();

        foreach (['display: flex', 'display: grid', 'aspect-ratio', 'object-fit', 'position: absolute'] as $unsupported) {
            $this->assertStringNotContainsString($unsupported, $css, sprintf('The compiled email stylesheet uses "%s", which mail clients do not lay out.', $unsupported));
        }
    }

    /** @return string[] */
    private function declaredTokens(string $css): array
    {
        preg_match_all('/^\s*(--[a-z0-9-]+):/m', $css, $matches);

        return array_unique($matches[1]);
    }

    // Read outside a :root block, i.e. by a rule that paints something rather than by another token's value.
    // Only the reads with no fallback: "var(--alert-max-width, 700px)" is a deliberate override hook - the declaration stays valid with the token undeclared, and CssVariableResolver substitutes the fallback before the message is inlined
    /** @return string[] */
    private function tokensReadByRules(string $css): array
    {
        $rules = (string) preg_replace('/:root\s*\{[^}]*\}/', '', $css);
        preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*\)/', $rules, $matches);

        return array_unique($matches[1]);
    }

    private function compiled(): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/emails.css';
        $this->assertFileExists($path, 'emails.css is missing, the sass has not been compiled.');

        return (string) file_get_contents($path);
    }
}
