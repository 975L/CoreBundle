<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ProgressTrackerType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProgressTrackerTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new ProgressTrackerType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsEveryTrackerField(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'total', 'completed', 'note'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the ProgressTracker form");
        }
    }

    // The count is the whole point of the kind, where everything around it is trimming an editor may skip
    public function testOnlyTheTwoFiguresAreRequired(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'note'] as $field) {
            $this->assertFalse($added[$field]['required'], "\"$field\" should be optional");
        }

        foreach (['total', 'completed'] as $field) {
            $this->assertArrayNotHasKey('required', $added[$field], "\"$field\" should keep the default required state");
        }
    }

    // Past the ceiling the segments are thinner than the gaps parting them - the template clamps to the same figure, so a value never reaching this form lands on the same row
    public function testBothFiguresAreCappedAtTheSameCeilingTheTemplateClampsTo(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(1, $added['total']['attr']['min']);
        $this->assertSame(ProgressTrackerType::MAX_SEGMENTS, $added['total']['attr']['max']);
        $this->assertSame(0, $added['completed']['attr']['min']);
        $this->assertSame(ProgressTrackerType::MAX_SEGMENTS, $added['completed']['attr']['max']);
    }

    // Reused rather than near-duplicated: "label.eyebrow" and "label.title" already name the same two fields on other kinds
    public function testTheTrimmingFieldsReuseTheSharedLabelKeys(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame('label.eyebrow', $added['eyebrow']['label']);
        $this->assertSame('label.title', $added['title']['label']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new ProgressTrackerType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
