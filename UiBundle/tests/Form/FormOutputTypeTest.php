<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Form\FormOutputType;
use c975L\UiBundle\Form\Util\FormTranslationBuilder;
use c975L\UiBundle\Service\FormTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class FormOutputTypeTest extends TestCase
{
    private function buildStaticFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        new FormOutputType(new FormTranslationBuilder(new FormTranslator()))->buildForm($builder, ['translation_locale' => null]);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with $output, returning every field added on the inner (event) form - mirrors what happens when a row of the "outputs" collection is rendered
    private function firePreSetData(?FormOutput $output): array
    {
        $listener = null;
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnCallback(
            function (string $eventName, callable $callback) use (&$listener, $builder) {
                $listener = $callback;

                return $builder;
            }
        );

        new FormOutputType(new FormTranslationBuilder(new FormTranslator()))->buildForm($builder, ['translation_locale' => null]);

        $added = [];
        $innerForm = $this->createStub(FormInterface::class);
        $innerForm->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $innerForm) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $innerForm;
        });

        $listener(new PreSetDataEvent($innerForm, $output));

        return $added;
    }

    // The label, the expression and the unit each carry their column's own length, so an over-long value is refused on the screen rather than truncated at the INSERT
    public function testTheColumnLengthsAreEnforcedOnTheScreen(): void
    {
        $added = $this->buildStaticFields();

        $this->assertSame(100, $this->maxLengthOf($added['label']['options']['constraints']));
        $this->assertSame(500, $this->maxLengthOf($added['expression']['options']['constraints']));
        $this->assertSame(20, $this->maxLengthOf($added['unit']['options']['constraints']));
    }

    private function maxLengthOf(array $constraints): ?int
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Length) {
                return $constraint->max;
            }
        }

        return null;
    }

    // Everything but the label, the expression and the format is optional - an output printing a bare number carries no unit and no decimals
    public function testOnlyTheLabelExpressionAndFormatAreRequired(): void
    {
        $added = $this->buildStaticFields();

        $this->assertFalse($added['decimals']['options']['required']);
        $this->assertFalse($added['unit']['options']['required']);
        $this->assertFalse($added['visible']['options']['required']);
        $this->assertFalse($added['highlighted']['options']['required']);
    }

    // Same "ui-sort-position" hook the fields collection uses, which is what ea-sortable.js writes the new order into - and the order is load-bearing here, an expression only seeing the outputs above it
    public function testPositionCarriesTheSortableHook(): void
    {
        $added = $this->buildStaticFields();

        $this->assertSame('ui-sort-position', $added['position']['options']['attr']['class']);
    }

    public function testFormatChoicesCoversEveryFormOutputFormat(): void
    {
        $this->assertSame(FormOutput::FORMATS, array_values(FormOutputType::formatChoices()));
    }

    public function testIdFieldCarriesTheOutputId(): void
    {
        $output = new FormOutput()->setLabel('Yearly saving');
        $reflection = new \ReflectionProperty(FormOutput::class, 'id');
        $reflection->setValue($output, 42);

        $added = $this->firePreSetData($output);

        $this->assertSame(42, $added['id']['options']['data']);
    }

    // A row the admin has just added has no id yet, and the hidden field still has to be there for the reconciler to read back
    public function testIdFieldIsEmptyForAnOutputNotSavedYet(): void
    {
        $added = $this->firePreSetData(new FormOutput()->setLabel('Yearly saving'));

        $this->assertArrayHasKey('id', $added);
        $this->assertNull($added['id']['options']['data']);
    }

    // Unlike FormLinkType's entries, an output is a real entity - Symfony has to hydrate one, not an array
    public function testOutputsAreMappedToTheEntity(): void
    {
        $resolver = new OptionsResolver();
        new FormOutputType(new FormTranslationBuilder(new FormTranslator()))->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(FormOutput::class, $options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
