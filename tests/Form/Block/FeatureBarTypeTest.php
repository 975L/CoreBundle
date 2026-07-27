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
