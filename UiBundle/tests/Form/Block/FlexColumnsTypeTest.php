<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\FlexColumnsType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class FlexColumnsTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new FlexColumnsType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsEyebrowTitleAndAnchorFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'anchor'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the FlexColumns form");
        }
    }

    // Two columns saying one thing between them are set against each other's middle, where the default suits a row of parallel items; "top" carries no placeholder, an unset value having to keep meaning exactly that
    public function testVerticalAlignOffersTheThreeAlignmentsWithoutAPlaceholder(): void
    {
        $added = $this->buildAddedFields();

        $this->assertArrayHasKey('verticalAlign', $added);
        $this->assertSame(
            ['label.vertical_align_top' => 'top', 'label.vertical_align_middle' => 'middle', 'label.vertical_align_bottom' => 'bottom'],
            $added['verticalAlign']['options']['choices']
        );
        $this->assertFalse($added['verticalAlign']['options']['placeholder']);
    }

    // "slots" is the Block relation added by addSlotsSubForm(), not plain data on this form
    public function testBuildFormDoesNotAddASlotsField(): void
    {
        $added = $this->buildAddedFields();

        $this->assertArrayNotHasKey('slots', $added);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new FlexColumnsType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
