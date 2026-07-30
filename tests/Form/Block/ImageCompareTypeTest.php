<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ImageCompareType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class ImageCompareTypeTest extends TestCase
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

        (new ImageCompareType())->buildForm($builder, []);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with $initialData, returning the resulting (possibly defaulted) data - mirrors what happens when an ImageCompare block form is first displayed
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

        (new ImageCompareType())->buildForm($builder, []);

        $form = $this->createStub(FormInterface::class);
        $event = new PreSetDataEvent($form, $initialData);
        $listener($event);

        return $event->getData();
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['id', 'startPosition', 'beforeLabel', 'afterLabel', 'class'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the ImageCompare form");
        }
    }

    public function testPreSetDataGeneratesAUniqueIdForABrandNewComparator(): void
    {
        $data = $this->firePreSetData(null);

        $this->assertMatchesRegularExpression('/^image-compare-[0-9a-f]{8}$/', $data['id']);
    }

    public function testPreSetDataRegeneratesTheIdWhenItIsAnEmptyString(): void
    {
        $data = $this->firePreSetData(['id' => '']);

        $this->assertNotSame('', $data['id']);
        $this->assertMatchesRegularExpression('/^image-compare-[0-9a-f]{8}$/', $data['id']);
    }

    public function testPreSetDataDefaultsStartPositionToFiftyForABrandNewComparator(): void
    {
        $data = $this->firePreSetData([]);

        $this->assertSame(50, $data['startPosition']);
    }

    public function testPreSetDataPreservesAlreadySetValuesOnAnExistingComparator(): void
    {
        $existing = [
            'id' => 'image-compare-deadbeef',
            'startPosition' => 30,
            'beforeLabel' => 'Avant',
            'afterLabel' => 'Après',
        ];

        $data = $this->firePreSetData($existing);

        $this->assertSame($existing, $data);
    }

    public function testPreSetDataNormalizesNonArrayDataToAnArray(): void
    {
        $data = $this->firePreSetData(null);

        $this->assertSame(50, $data['startPosition']);
    }
}
