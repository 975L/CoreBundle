<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\FlipCardType;
use c975L\UiBundle\Form\Block\SliderType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlipCardTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        (new FlipCardType())->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['id', 'title', 'level', 'content', 'backTitle', 'backContent', 'ratio', 'radius', 'shadow', 'class', 'accent'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the FlipCard form");
        }
    }

    // Every face field is optional: a card with only a front is still a card, and the template renders it as a plain one rather than as a card with a button revealing nothing (see components/FlipCard/FlipCard.html.twig)
    public function testEveryFaceFieldIsOptional(): void
    {
        $added = $this->buildAddedFields();

        foreach (['id', 'title', 'content', 'backTitle', 'backContent'] as $field) {
            $this->assertFalse($added[$field]['required'], "\"$field\" should be optional");
        }
    }

    // One level for both faces, so the two headings of the same card never sit at two different depths
    public function testBothFacesShareASingleTitleLevel(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4'], $added['level']['choices']);
        $this->assertArrayNotHasKey('backLevel', $added);
    }

    // The very list a slider offers, reused rather than restated - a card gaining a ratio the slider has and this one hasn't is exactly what a copied list drifts into
    public function testTheRatioChoicesAreTheSliderSOwn(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(SliderType::RATIO_CHOICES, $added['ratio']['choices']);
        $this->assertSame('free', reset($added['ratio']['choices']), 'The free ratio should stay first, an unset value falling back on it.');
    }

    // A slider's ratio crops an image, this one is a floor under the card's box - the same help text would say the wrong thing
    public function testTheRatioCarriesItsOwnHelpText(): void
    {
        $this->assertSame('label.flip_card_ratio_help', $this->buildAddedFields()['ratio']['help']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new FlipCardType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
