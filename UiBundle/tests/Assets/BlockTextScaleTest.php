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

// Three typographic scales live in the block layer, and the font a rule is set in is what decides between them.
// Everything set in the body font is sized in em, so it follows SiteBundle's --font-size-body and one value retunes the reading of the whole page. A title and an eyebrow are the design's own marks and must not be dragged along by it, so they keep their own lengths, each multiplied by the factor of its own family.
// A rule on the wrong side of the split is what this test is for: nothing renders wrong, the text simply stops answering the one setting meant to size it - or moves with a setting that was never meant to reach it
class BlockTextScaleTest extends TestCase
{
    private const string STYLESHEET = 'sass/_page-sections.scss';

    // A title/eyebrow says so with its own font-family, every one of them stating it rather than inheriting
    private const string DESIGN_MARK = '/font-family:\s*var\(--font-family-(title|accent)\)/';

    public function testEveryBodyFontSizeInTheBlockLayerIsRelativeToTheBodyCopy(): void
    {
        $checked = 0;

        foreach ($this->leafRules() as [$selector, $body]) {
            if (1 !== preg_match('/font-size:\s*([^;]+);/', $body, $matches)) {
                continue;
            }

            // The design's own marks, pinned on purpose
            if (1 === preg_match(self::DESIGN_MARK, $body)) {
                continue;
            }

            $size = trim($matches[1]);

            // Read through a token, so the length inside it is the bundle's default and a theme's own call
            if (str_contains($size, 'var(--')) {
                continue;
            }

            ++$checked;
            $this->assertStringNotContainsString(
                'px',
                $size,
                sprintf('"%s" sizes body-font text in px, so it no longer follows --font-size-body. Use em, or put it behind a token of its own.', $selector)
            );
        }

        // A parser that matched nothing would pass this test without reading a single rule
        $this->assertGreaterThan(5, $checked, 'No body-font rule was checked at all - the stylesheet parser is not finding them any more.');
    }

    // The design's marks are the other half of the split, and they must not drift into em either: sized against the body copy, a title would grow with it, which is exactly what keeping them in their own lengths prevents
    public function testEveryTitleAndEyebrowInTheBlockLayerStaysPinned(): void
    {
        foreach ($this->leafRules() as [$selector, $body]) {
            if (1 !== preg_match('/font-size:\s*([^;]+);/', $body, $matches) || 1 !== preg_match(self::DESIGN_MARK, $body)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/[\d.]+em\b/',
                str_replace('rem', '', trim($matches[1])),
                sprintf('"%s" sizes a title or an eyebrow in em, so it grows with the body copy instead of holding the design\'s own scale.', $selector)
            );
        }
    }

    // Those lengths are a hierarchy, so the family moves by a factor rather than by one shared size. A rule left out of it is the one that stops when every other title does not, which is worse than none of them moving
    public function testEveryTitleAndEyebrowCarriesTheScaleOfItsFamily(): void
    {
        $checked = 0;

        foreach ($this->leafRules() as [$selector, $body]) {
            if (1 !== preg_match('/font-size:\s*([^;]+);/', $body, $matches) || 1 !== preg_match(self::DESIGN_MARK, $body, $family)) {
                continue;
            }

            ++$checked;
            $scale = 'title' === $family[1] ? '--font-size-title-scale' : '--font-size-eyebrow-scale';
            $this->assertStringContainsString(
                sprintf('var(%s, 1)', $scale),
                $matches[1],
                sprintf('"%s" does not carry %s, so it stays behind when the rest of its family is scaled.', $selector, $scale)
            );
        }

        $this->assertGreaterThan(5, $checked, 'No title or eyebrow rule was checked at all - the stylesheet parser is not finding them any more.');
    }

    /**
     * Every innermost rule as [selector, declarations] - a block holding another one (an @media wrapper) is
     * walked into rather than read, so a rule inside a breakpoint is checked like any other.
     *
     * @return list<array{string, string}>
     */
    private function leafRules(): array
    {
        $path = \dirname(__DIR__, 2) . '/' . self::STYLESHEET;
        $this->assertFileExists($path);

        $scss = (string) file_get_contents($path);
        $scss = (string) preg_replace('#/\*.*?\*/#s', '', $scss);
        $scss = (string) preg_replace('#//[^\n]*#', '', $scss);

        $rules = [];
        $stack = [];
        $selectorStart = 0;

        for ($i = 0, $length = \strlen($scss); $i < $length; ++$i) {
            if ('{' === $scss[$i]) {
                if ([] !== $stack) {
                    $stack[\count($stack) - 1]['nested'] = true;
                }

                $stack[] = [
                    'selector' => trim((string) preg_replace('/\s+/', ' ', substr($scss, $selectorStart, $i - $selectorStart))),
                    'start' => $i + 1,
                    'nested' => false,
                ];
                $selectorStart = $i + 1;
            } elseif ('}' === $scss[$i]) {
                $rule = array_pop($stack);
                if (null !== $rule && !$rule['nested']) {
                    $rules[] = [$rule['selector'], substr($scss, $rule['start'], $i - $rule['start'])];
                }
                $selectorStart = $i + 1;
            }
        }

        return $rules;
    }
}
