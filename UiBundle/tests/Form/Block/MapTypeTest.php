<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\MapPointType;
use c975L\UiBundle\Form\Block\MapType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class MapTypeTest extends TestCase
{
    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['anchor', 'title', 'height', 'zoom', 'points'] as $field) {
            $this->assertArrayHasKey($field, $added, sprintf('"%s" should be added to the Map form', $field));
        }
    }

    // An API key and a billing account are a site-wide decision, taken once in the settings - a field here would ask an editor composing a page to take it again on every map they place
    public function testTheProviderAndItsKeyAreNoFieldsOfThisForm(): void
    {
        $added = $this->buildAddedFields();

        $this->assertArrayNotHasKey('provider', $added);
        $this->assertArrayNotHasKey('apiKey', $added);
    }

    public function testThePlacesAreACollectionOfMapPoints(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(MapPointType::class, $added['points']['entry_type']);
        $this->assertTrue($added['points']['allow_add']);
        $this->assertTrue($added['points']['allow_delete']);
    }

    // The three heights are classes the stylesheet draws (see sass/_map.scss): one offered here and never written there leaves the canvas with no height at all, which every tile library collapses to nothing
    public function testEveryHeightOfferedIsOneTheStylesheetDraws(): void
    {
        $stylesheet = (string) file_get_contents(\dirname(__DIR__, 3) . '/sass/_map.scss');

        foreach ($this->buildAddedFields()['height']['choices'] as $value) {
            $this->assertStringContainsString('.ui-map--' . $value . ' .ui-map__canvas', $stylesheet, sprintf('The "%s" height has no rule of its own.', $value));
        }
    }

    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new MapType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }
}
