<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ComparisonRowType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComparisonRowTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new ComparisonRowType()->buildForm($builder, []);

        return $added;
    }

    // A row is its label, then its cells on one optional line, one per column
    public function testTheRowIsALabelThenItsCellsOnOneLine(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['label', 'cells'], array_keys($added));
        $this->assertSame(TextType::class, $added['cells']['type']);
        $this->assertFalse($added['cells']['options']['required']);
        $this->assertSame('help.comparison_cells', $added['cells']['options']['help']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new ComparisonRowType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
