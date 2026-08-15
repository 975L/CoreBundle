<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\CardType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CardTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new CardType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['id', 'title', 'level', 'content', 'url', 'target', 'buttonLabel', 'size', 'radius', 'shadow', 'class', 'accent', 'cssClasses'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the Card form");
        }
    }

    // A card carries both class fields, and the split is what they answer: the closed lists are its shape, which the bundle draws itself, where the free one is its palette - a card standing for a scope only the consuming site declares
    public function testItCarriesBothTheClosedListsAndTheFreeSiteClasses(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame('label.css_classes_free', $added['cssClasses']['label']);
        $this->assertFalse($added['cssClasses']['required']);
    }

    public function testTeaserFieldsAreOptional(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['id']['required']);
        $this->assertFalse($added['title']['required']);
        $this->assertFalse($added['url']['required']);
        $this->assertFalse($added['target']['required']);
        $this->assertFalse($added['buttonLabel']['required']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new CardType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
