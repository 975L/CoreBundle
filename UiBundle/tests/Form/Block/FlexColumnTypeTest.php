<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\FlexColumnType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlexColumnTypeTest extends TestCase
{
    // Captures every builder->add() call's options
    private function buildAddedOptions(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new FlexColumnType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormOffersExactlyTheDeclaredWidths(): void
    {
        $added = $this->buildAddedOptions();

        $this->assertSame(FlexColumnType::WIDTHS, array_values($added['columnWidth']['choices']));
    }

    // An empty field must keep meaning "no width of my own", so an older column still shares evenly
    public function testTheEvenColumnIsAPlaceholderRatherThanAChoiceOfItsOwn(): void
    {
        $added = $this->buildAddedOptions();

        $this->assertSame('label.column_width_auto', $added['columnWidth']['placeholder']);
        $this->assertFalse($added['columnWidth']['required']);
        $this->assertNotContains('', $added['columnWidth']['choices']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new FlexColumnType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
