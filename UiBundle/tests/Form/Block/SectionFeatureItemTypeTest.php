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
use c975L\UiBundle\Form\IconPickerType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SectionFeatureItemTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        (new SectionFeatureItemType())->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsIconTitleAndTextFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['icon', 'title', 'text'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the SectionFeatureItem form");
        }
    }

    // Reuses the existing icon picker (see ButtonType) instead of a per-card media upload
    public function testIconFieldReusesTheExistingIconPickerType(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(IconPickerType::class, $added['icon']['type']);
        $this->assertFalse($added['icon']['options']['required']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new SectionFeatureItemType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
