<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ComparisonColumnType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComparisonColumnTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new ComparisonColumnType()->buildForm($builder, []);

        return $added;
    }

    // An offer is its name alone, heading its column
    public function testTheColumnIsItsLabelAlone(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['label'], array_keys($added));
        $this->assertSame(TextType::class, $added['label']['type']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new ComparisonColumnType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
