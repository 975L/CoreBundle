<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Form\FormFieldType;
use c975L\UiBundle\Form\Util\FormTranslationBuilder;
use c975L\UiBundle\Service\FormTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

class FormFieldTypeTest extends TestCase
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

        new FormFieldType(new FormTranslationBuilder(new FormTranslator()))->buildForm($builder, ['translation_locale' => null]);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with $field, returning every field added on the inner (event) form - mirrors what happens when a row of the "fields" collection is rendered
    private function firePreSetData(?FormField $field): array
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

        new FormFieldType(new FormTranslationBuilder(new FormTranslator()))->buildForm($builder, ['translation_locale' => null]);

        $added = [];
        $innerForm = $this->createStub(FormInterface::class);
        $innerForm->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $innerForm) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $innerForm;
        });

        $listener(new PreSetDataEvent($innerForm, $field));

        return $added;
    }

    public function testRestrictedMarkerIsAlwaysDisabled(): void
    {
        $added = $this->buildStaticFields();

        $this->assertTrue($added['restricted']['options']['disabled']);
    }

    public function testUrlFieldIsOptional(): void
    {
        $added = $this->buildStaticFields();

        $this->assertFalse($added['url']['options']['required']);
    }

    public function testTypeFieldIsEnabledForAnOrdinaryField(): void
    {
        $field = new FormField()->setLabel('Phone')->setName('phone');

        $added = $this->firePreSetData($field);

        $this->assertFalse($added['type']['options']['disabled']);
    }

    public function testTypeFieldIsDisabledForARestrictedField(): void
    {
        $field = new FormField()->setLabel('Email')->setName('email')->setRestricted(true);

        $added = $this->firePreSetData($field);

        $this->assertTrue($added['type']['options']['disabled']);
    }

    public function testTypeFieldIsEnabledWhenNoFieldYetExists(): void
    {
        $added = $this->firePreSetData(null);

        $this->assertFalse($added['type']['options']['disabled']);
    }

    // Shared with FormFieldTemplateCrudController's own "type" ChoiceField - every real FormField::TYPES value must have a translated choice, and nothing else
    public function testTypeChoicesCoversEveryFormFieldType(): void
    {
        $this->assertSame(FormField::TYPES, array_values(FormFieldType::typeChoices()));
    }

    // Used by FormFieldTemplateCrudController's EasyAdmin ChoiceField via setTranslatableChoices() - its plain-string choices (typeChoices()) don't translate there since EasyAdmin's own CRUD-level domain isn't "ui" the way this form's own translation_domain is (see configureOptions())
    public function testTranslatableTypeChoicesCoversTheSameTypesAsTypeChoices(): void
    {
        $translatable = FormFieldType::translatableTypeChoices();

        $this->assertSame(FormField::TYPES, array_keys($translatable));
        foreach ($translatable as $type => $label) {
            $this->assertInstanceOf(TranslatableInterface::class, $label);
        }
    }

    public function testIdFieldCarriesTheFieldId(): void
    {
        $field = new FormField()->setLabel('Phone')->setName('phone');
        $reflection = new \ReflectionProperty(FormField::class, 'id');
        $reflection->setValue($field, 42);

        $added = $this->firePreSetData($field);

        $this->assertSame(42, $added['id']['options']['data']);
    }
}
