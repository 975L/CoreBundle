<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\SliderType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class SliderTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null) use (&$added, $builder) {
            $added[$name] = $type;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        (new SliderType())->buildForm($builder, []);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with $initialData, returning the resulting (possibly defaulted) data - mirrors what happens when a Slider block form is first displayed
    private function firePreSetData(mixed $initialData): array
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

        (new SliderType())->buildForm($builder, []);

        $form = $this->createStub(FormInterface::class);
        $event = new PreSetDataEvent($form, $initialData);
        $listener($event);

        return $event->getData();
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['id', 'duration', 'ratio', 'layout', 'class'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the Slider form");
        }
    }

    public function testLayoutFieldOffersDefaultAndFreeflowChoices(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        (new SliderType())->buildForm($builder, []);

        $this->assertSame(SliderType::LAYOUT_CHOICES, $added['layout']['choices']);
    }

    public function testPreSetDataGeneratesAUniqueIdForABrandNewSlider(): void
    {
        $data = $this->firePreSetData(null);

        $this->assertMatchesRegularExpression('/^slider-[0-9a-f]{8}$/', $data['id']);
    }

    public function testPreSetDataRegeneratesTheIdWhenItIsAnEmptyString(): void
    {
        $data = $this->firePreSetData(['id' => '']);

        $this->assertNotSame('', $data['id']);
        $this->assertMatchesRegularExpression('/^slider-[0-9a-f]{8}$/', $data['id']);
    }

    // Documents the actual current defaults applied to a brand new slider (no "id" set at all) - note "duration" defaults to 0 (autoplay disabled, see assets/js/slider.js), not the 5000ms the field used to default to via its "data" option before this was moved into PRE_SET_DATA
    public function testPreSetDataDefaultsForABrandNewSlider(): void
    {
        $data = $this->firePreSetData([]);

        $this->assertSame(0, $data['duration']);
        $this->assertSame('free', $data['ratio']);
        $this->assertSame('default', $data['layout']);
    }

    public function testPreSetDataPreservesAlreadySetValuesOnAnExistingSlider(): void
    {
        $existing = [
            'id' => 'slider-deadbeef',
            'duration' => 8000,
            'ratio' => '16-9',
            'layout' => 'freeflow',
        ];

        $data = $this->firePreSetData($existing);

        $this->assertSame($existing, $data);
    }

    public function testPreSetDataNormalizesNonArrayDataToAnArray(): void
    {
        $data = $this->firePreSetData(null);

        $this->assertSame('free', $data['ratio']);
        $this->assertSame('default', $data['layout']);
    }
}
