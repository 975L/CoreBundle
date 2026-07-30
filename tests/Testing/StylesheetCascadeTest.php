<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Testing;

use c975L\UiBundle\Testing\StylesheetCascade;
use PHPUnit\Framework\TestCase;

// The engine every stylesheet test reads the cascade through, here and in the bundles depending on this one: a specificity read wrong turns a real collision into a silent pass
class StylesheetCascadeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/stylesheet-cascade-test-' . uniqid();
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    // Writes a stylesheet into the sandbox and returns its path
    private function createStylesheet(string $name, string $css): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $css);

        return $path;
    }

    public function testFromFilesReadsRulesInLoadOrderAndNamesTheirSheet(): void
    {
        $cascade = StylesheetCascade::fromFiles(
            $this->createStylesheet('ui.css', '.slider{margin:1em auto}'),
            $this->createStylesheet('site.css', '.slider{margin:0}')
        );

        $rules = $cascade->rules();

        $this->assertCount(2, $rules);
        $this->assertSame('ui.css', $rules[0]['source']);
        $this->assertSame('site.css', $rules[1]['source']);
        $this->assertSame(['.slider'], $rules[1]['selectors']);
        $this->assertSame('0', $rules[1]['declarations']['margin']);
        $this->assertGreaterThan($rules[0]['order'], $rules[1]['order']);
    }

    // A stylesheet nobody compiled is a broken run, not an empty one - a cascade read off nothing would pass every test built on it
    public function testFromFilesThrowsWhenTheStylesheetIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the sass has not been compiled');

        StylesheetCascade::fromFiles($this->directory . '/never-compiled.css');
    }

    // Responsive and state overrides are deliberate, and a layered rule loses to an unlayered one whatever the source order
    public function testFromFilesSkipsRulesNestedInAtRules(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet(
            'ui.css',
            '.slider{margin:1em auto}@media (max-width:600px){.slider{margin:0}}@layer ui-defaults{:root{--x:1}}'
        ));

        $this->assertCount(1, $cascade->rules());
    }

    public function testSpecificityCountsIdsClassesAndTypesSeparately(): void
    {
        $this->assertSame([0, 1, 0], StylesheetCascade::specificity('.slider'));
        $this->assertSame([0, 1, 1], StylesheetCascade::specificity('.blocks > section'));
        $this->assertSame([1, 0, 0], StylesheetCascade::specificity('#main'));
        $this->assertSame([0, 2, 0], StylesheetCascade::specificity('.hero.hero--has-bg'));
    }

    // ":is()"/":not()"/":has()" count as their strongest argument, ":where()" as nothing at all - read either the other way round and a whole family of rules is compared wrong
    public function testSpecificityFollowsTheFunctionalPseudoClassRules(): void
    {
        $this->assertSame([0, 1, 1], StylesheetCascade::specificity(':is(.blocks, .block-animation) > section'));
        $this->assertSame([0, 0, 1], StylesheetCascade::specificity(':where(.blocks) > section'));
        $this->assertSame([0, 1, 0], StylesheetCascade::specificity(':not(.slider)'));
    }

    public function testOverrulesPrefersSpecificityThenSourceOrder(): void
    {
        $weak = ['specificity' => [0, 1, 0], 'order' => 10];
        $strong = ['specificity' => [0, 1, 1], 'order' => 0];
        $later = ['specificity' => [0, 1, 0], 'order' => 20];

        $this->assertTrue(StylesheetCascade::overrules($strong, $weak));
        $this->assertFalse(StylesheetCascade::overrules($weak, $strong));
        $this->assertTrue(StylesheetCascade::overrules($later, $weak));
        $this->assertFalse(StylesheetCascade::overrules($weak, $later));
    }

    public function testLayoutContainerClassesReadsTheFlexAndGridClasses(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet(
            'ui.css',
            '.cards{display:grid}.feature-bar{display:flex}.chip{display:inline-flex}.slider{display:block}.blocks .grid{display:grid}'
        ));

        $containers = $cascade->layoutContainerClasses();

        $this->assertSame(['cards', 'feature-bar', 'chip'], array_keys($containers));
    }

    public function testTransparentWrapperClassesReadsTheDisplayContentsClasses(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet(
            'ui.css',
            '.blocks{display:contents}.block-animation{display:contents}.slider{display:block}'
        ));

        $this->assertSame(['blocks', 'block-animation'], array_keys($cascade->transparentWrapperClasses()));
    }

    // The rightmost compound is the one the rule actually styles; everything left of it is context no reading without a DOM can resolve
    public function testCanMatchReadsTheSubjectCompoundOfTheSelector(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet('ui.css', '.x{margin:0}'));

        $this->assertTrue($cascade->canMatch(['.blocks > section'], 'slider', ['section']));
        $this->assertTrue($cascade->canMatch(['.blocks > .slider'], 'slider', ['section']));
        $this->assertFalse($cascade->canMatch(['.blocks > article'], 'slider', ['section']));
        $this->assertFalse($cascade->canMatch(['section > .blocks'], 'slider', ['section']));
    }

    // A tag built by a template expression is read as "any tag", an unresolved collision costing more than a reported one
    public function testCanMatchTreatsTheWildcardTagAsAnyTag(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet('ui.css', '.x{margin:0}'));

        $this->assertTrue($cascade->canMatch(['.blocks > article'], 'slider', ['*']));
    }

    public function testCanMatchSkipsASelectorScopedUnderALayoutContainer(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet('ui.css', '.cards{display:grid}'));

        $this->assertFalse($cascade->canMatch(['.cards > div'], 'card', ['div'], ['cards' => true]));
        $this->assertTrue($cascade->canMatch(['.blocks > div'], 'card', ['div'], ['cards' => true]));
    }

    // Under a concrete styled ancestor a tag-only rule is a coincidence of tag names; under a "display: contents" wrapper, or under nothing, it is the shape of the reset that flattened the slider
    public function testCanMatchSkipsATagOnlyRuleOnlyUnderAConcreteAncestor(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet('ui.css', '.blocks{display:contents}'));
        $wrappers = ['blocks' => true];

        $this->assertFalse($cascade->canMatch(['.menu-site-tagline > section'], 'slider', ['section'], [], $wrappers));
        $this->assertTrue($cascade->canMatch(['.blocks > section'], 'slider', ['section'], [], $wrappers));
        $this->assertTrue($cascade->canMatch(['main section'], 'slider', ['section'], [], $wrappers));
        $this->assertTrue($cascade->canMatch(['.menu-site-tagline > .slider'], 'slider', ['section'], [], $wrappers));
    }

    // A variant is named by its base too, so a rule reaching either reaches the element carrying both
    public function testCanMatchAcceptsEveryClassTheElementCarries(): void
    {
        $cascade = StylesheetCascade::fromFiles($this->createStylesheet('ui.css', '.x{margin:0}'));

        $this->assertTrue($cascade->canMatch(['.hero'], ['hero', 'hero--has-bg'], ['section']));
        $this->assertTrue($cascade->canMatch(['.hero--has-bg'], ['hero', 'hero--has-bg'], ['section']));
        $this->assertFalse($cascade->canMatch(['.slider'], ['hero', 'hero--has-bg'], ['section']));
    }
}
