<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\SectionFeatureItemType;
use c975L\UiBundle\Form\Block\SectionFeaturesType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SectionFeaturesTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new SectionFeaturesType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsEyebrowTitleCardsAndAnchorFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'cards', 'anchor'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the SectionFeatures form");
        }
    }

    public function testCardsFieldIsACollectionOfSectionFeatureItemType(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(CollectionType::class, $added['cards']['type']);
        $this->assertSame(SectionFeatureItemType::class, $added['cards']['options']['entry_type']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new SectionFeaturesType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
