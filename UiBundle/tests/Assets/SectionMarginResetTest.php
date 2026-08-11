<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\ComponentCenteringAnalyzer;
use c975L\UiBundle\Testing\StylesheetCascade;
use PHPUnit\Framework\TestCase;

// Each display:contents wrapper must be named in the margin reset, or its blocks get the gap back
class SectionMarginResetTest extends TestCase
{
    // The wrapper half of the reset, the section kinds it then names being read off the compiled sheet
    private const RESET_WRAPPERS = 'main :is(.blocks, .block-animation, .block-editable) > :is(';

    // Where each transparent wrapper is declared
    private const WRAPPER_SHEETS = ['_animations-media.scss', '_block-edit-overlay.scss', '_page-sections.scss'];

    public function testTheResetIsCompiledIntoBothStylesheets(): void
    {
        foreach (['styles.css', 'styles.min.css'] as $file) {
            $this->assertStringContainsString(
                '{margin-block:0}',
                $this->reset($file),
                sprintf('"%s" no longer drops the page-wide section margin inside a block wrapper.', $file)
            );
        }
    }

    // A rule turning a bare class transparent is such a wrapper; a descendant selector is not
    public function testEveryTransparentWrapperIsNamedByTheReset(): void
    {
        foreach (self::WRAPPER_SHEETS as $sheet) {
            $sass = (string) file_get_contents(dirname(__DIR__, 2) . '/sass/' . $sheet);
            preg_match_all('/^\.([a-z0-9_-]+)\s*\{[^}]*display:\s*contents/mi', $sass, $matches);

            foreach ($matches[1] as $wrapper) {
                $this->assertStringContainsString(
                    '.' . $wrapper,
                    self::RESET_WRAPPERS,
                    sprintf('".%s" is display:contents in "%s" but is not named by the section margin reset.', $wrapper, $sheet)
                );
            }
        }
    }

    // Those wrappers stack around one block - .block-editable, then .block-animation - and each measures as a zero rect, so the edit button descends until something painted is measured rather than reading one fixed child
    public function testTheEditOverlayMeasuresPastEveryTransparentWrapper(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/block-edit-overlay.js');

        $this->assertStringContainsString(
            'while (0 === rect.width && 0 === rect.height && measured.firstElementChild)',
            $js,
            'The overlay measures a fixed child, so a block under two stacked transparent wrappers gets its button at 0,0.'
        );
    }

    // Block axis only: on the inline axis the reset outranks every section laying itself out there, whether it centers itself through "margin: … auto" (the slider) or breaks out of the measure through a negative margin (the hero with a background, the colored flats) - which is what the "margin: 0" shorthand silently did until v1.12.3
    public function testTheResetNeverWritesTheInlineAxis(): void
    {
        foreach (['styles.css', 'styles.min.css'] as $file) {
            $declarations = substr($this->reset($file), (int) strpos($this->reset($file), '{'));

            foreach (['margin:', 'margin-inline', 'margin-left', 'margin-right'] as $property) {
                $this->assertStringNotContainsString(
                    $property,
                    $declarations,
                    sprintf('The section margin reset writes "%s" in "%s", left-aligning every section that lays itself out on the inline axis.', $property, $file)
                );
            }
        }
    }

    // The reset names its kinds one by one rather than matching "section", which would strip the vertical room the slider sets for itself. A kind added later and left out of that list keeps SiteBundle's page-wide "1em", parting two flats meant to touch - with nothing in this bundle's sass to show why.
    public function testEverySectionKindIsNamedByTheReset(): void
    {
        $root = dirname(__DIR__, 2);
        $reset = $this->reset('styles.min.css');
        $cascade = StylesheetCascade::fromFiles($root . '/public/css/styles.css');

        foreach (ComponentCenteringAnalyzer::tagsByClass($root . '/templates/components') as $class => $tags) {
            // Only what is rendered as a <section>: SiteBundle's rule reaches nothing else
            if (!in_array('section', $tags, true) || $this->setsItsOwnBlockMargin($cascade, (string) $class)) {
                continue;
            }

            $this->assertStringContainsString(
                '.' . $class,
                $reset,
                sprintf('".%s" is rendered as a <section> but is named neither by the margin reset nor by a block margin of its own.', $class)
            );
        }
    }

    // Its own vertical rhythm is a deliberate opt-out of the reset, which is how the slider keeps its --slider-margin-block, and how .feature-bar keeps the margin its step is made of. Both edges have to be settled: SiteBundle's shorthand writes them both, so a kind stating one only still takes "1em" on the other
    private function setsItsOwnBlockMargin(StylesheetCascade $cascade, string $class): bool
    {
        $edges = [];

        foreach ($cascade->rules() as $rule) {
            if (!in_array('.' . $class, $rule['selectors'], true)) {
                continue;
            }

            foreach (['margin', 'margin-block'] as $property) {
                if (isset($rule['declarations'][$property])) {
                    return true;
                }
            }

            foreach (['top' => ['margin-top', 'margin-block-start'], 'bottom' => ['margin-bottom', 'margin-block-end']] as $edge => $properties) {
                foreach ($properties as $property) {
                    if (isset($rule['declarations'][$property])) {
                        $edges[$edge] = true;
                    }
                }
            }
        }

        return 2 === count($edges);
    }

    // The whole compiled rule, selector and declarations, normalized so the same assertions hold on both sheets
    private function reset(string $file): string
    {
        $wrappers = str_replace(' ', '', self::RESET_WRAPPERS);
        preg_match('/' . preg_quote($wrappers, '/') . '[^{]*\{[^}]*\}/', $this->normalize($file), $matches);

        $this->assertNotEmpty($matches, sprintf('"%s" no longer holds the section margin reset at all.', $file));

        return $matches[0];
    }

    // Normalized so the same assertion holds on the expanded sheet and on the minified one
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/;\}/', '}', (string) preg_replace('/\s+/', '', $css));
    }
}
