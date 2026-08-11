<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Testing;

use c975L\UiBundle\Testing\ComponentCenteringAnalyzer;
use c975L\UiBundle\Testing\StylesheetCascade;
use PHPUnit\Framework\TestCase;

// ComponentCenteringTest runs this analyzer over the real compiled sheet and asserts it finds nothing, which an analyzer returning nothing at all would also satisfy - these run it over sheets written to collide, so what is being locked is that it reports the collision and skips what only looks like one
class ComponentCenteringAnalyzerTest extends TestCase
{
    private string $directory;

    // Sandboxes each test behind its own throwaway directory, the cascade reading from real files
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/component-centering-analyzer-test-' . uniqid();
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    // Analyses the given stylesheet against the given class-to-tags map, as the real test does off the compiled sheet and the component templates
    private function analyse(string $css, array $tagsByClass): array
    {
        $path = $this->directory . '/styles-' . uniqid() . '.css';
        file_put_contents($path, $css);

        return new ComponentCenteringAnalyzer(StylesheetCascade::fromFiles($path))->analyse($tagsByClass);
    }

    // The v1.12.0 defect itself: a page-wide reset naming the tag alone, stronger than the component's own rule, writing the shorthand over the "auto" the slider is centered on
    public function testReportsAStrongerRuleWritingTheMarginShorthandOverACentering(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.slider{max-width:800px;margin:1em auto 3em}.blocks > section{margin:0}',
            ['slider' => ['section']]
        );

        $this->assertSame(['slider'], $result['centered']);
        $this->assertCount(1, $result['violations']);
        $this->assertSame('slider', $result['violations'][0]['class']);
        $this->assertSame('margin: 1em auto 3em', $result['violations'][0]['centering']);
        $this->assertSame('margin: 0', $result['violations'][0]['override']);
        $this->assertSame('centered by', $result['violations'][0]['kind']);
    }

    // The fix that shipped with it: the same reset restricted to the block axis leaves the inline one alone
    public function testReadsAResetKeptOffTheInlineAxisAsNoCollision(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.slider{max-width:800px;margin:1em auto 3em}.blocks > section{margin-block:0}',
            ['slider' => ['section']]
        );

        $this->assertSame(['slider'], $result['centered']);
        $this->assertSame([], $result['violations']);
    }

    // A responsive override is somebody's deliberate decision at that width, not the accident this looks for
    public function testSkipsARuleNestedInAMediaQuery(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.slider{max-width:800px;margin:1em auto 3em}@media (max-width:600px){.blocks > section{margin:0}}',
            ['slider' => ['section']]
        );

        $this->assertSame([], $result['violations']);
    }

    // A rule the centering already beats never reaches the element, whatever it writes
    public function testSkipsAWeakerRule(): void
    {
        $result = $this->analyse(
            '.slider{max-width:800px;margin:1em auto 3em}section{margin:0}',
            ['slider' => ['section']]
        );

        $this->assertSame(['slider'], $result['centered']);
        $this->assertSame([], $result['violations']);
    }

    // A breakout is a layout to protect just as a centering is: the same shorthand takes the negative margin a full-bleed flat is laid out past its measure on
    public function testReportsARuleWritingOverANegativeMarginBreakout(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.hero--has-bg{margin-inline:-50vw}.blocks > section{margin:0}',
            ['hero--has-bg' => ['section']]
        );

        $this->assertSame(['.hero--has-bg'], $result['breakouts']);
        $this->assertCount(1, $result['violations']);
        $this->assertSame('broken out of the measure by', $result['violations'][0]['kind']);
        $this->assertSame('margin-inline: -50vw', $result['violations'][0]['centering']);
    }

    // A pixel nudge placing a handle or hiding a label is not a breakout, and reporting it would bury the real ones
    public function testDoesNotReadAPixelNegativeMarginAsABreakout(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.slider-handle{margin-inline:-12px}.blocks > section{margin:0}',
            ['slider-handle' => ['section']]
        );

        $this->assertSame([], $result['breakouts']);
        $this->assertSame([], $result['violations']);
    }

    // An "auto" margin on a box with no measure of its own computes to zero, so there was never a centering to lose
    public function testIgnoresAnAutoMarginOnAComponentWithNoMeasure(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.btn{margin:1em auto}.blocks > section{margin:0}',
            ['btn' => ['section']]
        );

        $this->assertSame([], $result['centered']);
        $this->assertSame([], $result['violations']);
    }

    // As a grid item the card is placed by the container, so writing its margin there is a layout decision rather than an accident
    public function testSkipsAComponentLaidOutByItsFlexOrGridContainer(): void
    {
        $result = $this->analyse(
            '.card{max-width:400px;margin:1em auto}.cards{display:grid}.cards > div{margin:0}',
            ['card' => ['div']]
        );

        $this->assertSame(['card'], $result['centered']);
        $this->assertSame([], $result['violations']);
    }

    // A rule naming no class of its own reaches the component through its tag alone, which under a concrete styled ancestor is a coincidence of tag names - a slider is not going to turn up inside ".menu-site-tagline"
    public function testSkipsATagOnlyRuleScopedUnderAConcreteAncestor(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.menu-site-tagline{color:red}.slider{max-width:800px;margin:1em auto 3em}.menu-site-tagline > section{margin:0}',
            ['slider' => ['section']]
        );

        $this->assertSame([], $result['violations']);
    }

    // Names both sheets and both rules: a collision between bundles is unreadable without them
    public function testDescribeNamesTheComponentTheOverrideAndTheSheet(): void
    {
        $result = $this->analyse(
            '.blocks{display:contents}.slider{max-width:800px;margin:1em auto 3em}.blocks > section{margin:0}',
            ['slider' => ['section']]
        );

        $description = ComponentCenteringAnalyzer::describe($result['violations'][0]);

        $this->assertStringContainsString('".slider" is centered by "margin: 1em auto 3em"', $description);
        $this->assertStringContainsString('".blocks > section" writes "margin: 0" over it', $description);
        $this->assertStringContainsString('margin-block', $description);
    }

    // Half the sections pick their tag at runtime, so the literals of the expression are what names them - read only what is written out and the whole page-section family goes missing
    public function testTagsByClassResolvesATagBuiltByATwigExpression(): void
    {
        $templates = $this->directory . '/Section';
        mkdir($templates, 0777, true);
        file_put_contents($templates . '/Head.html.twig', "{% set tag = title ? 'section' : 'div' %}\n<{{ tag }} class=\"section-head\">x</{{ tag }}>");

        $tags = ComponentCenteringAnalyzer::tagsByClass($this->directory);

        unlink($templates . '/Head.html.twig');
        rmdir($templates);

        $found = $tags['section-head'] ?? [];
        sort($found);

        $this->assertSame(['div', 'section'], $found);
    }
}
