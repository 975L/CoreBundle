<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ContactHoursType;
use c975L\UiBundle\Service\ContactSnippetBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactHoursTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new ContactHoursType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $this->assertSame(['days', 'opens', 'closes'], array_keys($this->buildAddedFields()));
    }

    // The stored values are schema.org's own day names, so ContactSnippetBuilder needs no mapping of its own
    public function testDaysStoreSchemaOrgNamesAndTranslateTheirLabels(): void
    {
        $days = $this->buildAddedFields()['days'];

        $this->assertTrue($days['multiple']);
        $this->assertSame(ContactSnippetBuilder::DAYS, array_values($days['choices']));
        $this->assertSame(
            ['label.monday', 'label.tuesday', 'label.wednesday', 'label.thursday', 'label.friday', 'label.saturday', 'label.sunday'],
            array_keys($days['choices'])
        );
    }

    // A native time picker storing "HH:MM" straight into the block's JSON data, so no typed-in "9h"/"6pm" is ever guessed at
    public function testTimesAreStoredAsHoursAndMinutesStrings(): void
    {
        $added = $this->buildAddedFields();

        foreach (['opens', 'closes'] as $field) {
            $this->assertSame('single_text', $added[$field]['widget']);
            $this->assertSame('string', $added[$field]['input']);
            $this->assertSame('H:i', $added[$field]['input_format']);
            $this->assertFalse($added[$field]['with_seconds']);
        }
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $resolver = new OptionsResolver();
        new ContactHoursType()->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
