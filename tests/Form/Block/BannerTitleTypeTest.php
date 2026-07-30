<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\BannerTitleType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BannerTitleTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        (new BannerTitleType())->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsTitleLevelAndMaxHeightFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['title', 'level', 'maxHeight'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the BannerTitle form");
        }
    }

    public function testLevelFieldOffersH1H2H3Choices(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3'], $added['level']['choices']);
    }

    public function testMaxHeightFieldIsNotRequired(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['maxHeight']['required']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new BannerTitleType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
