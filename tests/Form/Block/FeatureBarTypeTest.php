<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\FeatureBarType;
use c975L\UiBundle\Form\Block\FeatureItemType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints\Count;

class FeatureBarTypeTest extends TestCase
{
    public function testBuildFormAddsAnItemsCollectionOfFeatureItemTypeAndAnAnchorField(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        (new FeatureBarType(new BlockAnchorSlugger(new AsciiSlugger())))->buildForm($builder, []);

        $this->assertArrayHasKey('items', $added);
        $this->assertArrayHasKey('background', $added);
        $this->assertSame(CollectionType::class, $added['items']['type']);
        $this->assertSame(FeatureItemType::class, $added['items']['options']['entry_type']);
        $this->assertTrue($added['items']['options']['allow_add']);
        $this->assertTrue($added['items']['options']['allow_delete']);
        $this->assertArrayHasKey('anchor', $added);
    }

    // The cap is a layout limit, locked here rather than left to whoever edits the grid rules
    public function testBuildFormCapsTheItemsCollectionWithACountConstraint(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        (new FeatureBarType(new BlockAnchorSlugger(new AsciiSlugger())))->buildForm($builder, []);

        $this->assertSame(5, FeatureBarType::MAX_ITEMS);
        $this->assertCount(1, $added['items']['options']['constraints']);
        $constraint = $added['items']['options']['constraints'][0];
        $this->assertInstanceOf(Count::class, $constraint);
        $this->assertSame(FeatureBarType::MAX_ITEMS, $constraint->max);
        $this->assertSame('text.feature_bar_items_max', $constraint->maxMessage);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new FeatureBarType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
