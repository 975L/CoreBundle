<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\PortfolioGridType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class PortfolioGridTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new PortfolioGridType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'linkLabel', 'linkUrl', 'anchor', 'level'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the PortfolioGrid form");
        }
    }

    // Every field is optional - the project cards themselves come from the block's medias, not this form
    public function testNoFieldIsRequired(): void
    {
        $added = $this->buildAddedFields();

        foreach ($added as $field => $options) {
            $this->assertFalse($options['required'], "\"$field\" should not be required");
        }
    }

    // The same field the "collection" block offers, so one design decision reads the same on both grids
    public function testLevelOffersAnAutomaticChoiceAndTheThreeHeadings(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame([
            'label.collection_item_level_auto' => '',
            'h2' => 'h2',
            'h3' => 'h3',
            'h4' => 'h4',
        ], $added['level']['choices']);
        $this->assertFalse($added['level']['placeholder']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new PortfolioGridType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
