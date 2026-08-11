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

        new BannerTitleType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsTitleLevelAndHeightFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['title', 'level', 'height'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the BannerTitle form");
        }
    }

    public function testLevelFieldOffersH1H2H3Choices(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3'], $added['level']['choices']);
    }

    public function testHeightFieldIsNotRequired(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['height']['required']);
    }

    // Three steps and no free length: what is stored has to be a value sass/_banner-title.scss already has a class for, a pixel value typed here being arbitrary CSS no class can carry
    public function testHeightFieldOffersTheThreeStepsAndNothingElse(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(BannerTitleType::HEIGHT_CHOICES, $added['height']['choices']);
        $this->assertSame(['small', 'medium', 'large'], array_values(BannerTitleType::HEIGHT_CHOICES));
    }

    // "Automatic" is the placeholder rather than a choice: an unset value keeps the floor the stylesheet sets, which is what every banner rendered before this field existed already stood at
    public function testHeightFieldKeepsTheAutomaticDefaultAsItsPlaceholder(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame('label.banner_height_auto', $added['height']['placeholder']);
        $this->assertNotContains('', BannerTitleType::HEIGHT_CHOICES, 'An empty choice would store a value for what is the absence of one.');
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
