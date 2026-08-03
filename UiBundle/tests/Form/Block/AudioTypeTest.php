<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\AudioType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AudioTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        (new AudioType())->buildForm($builder, []);

        return $added;
    }

    // Only display fields, the same ones as the video kinds - no width/height, an <audio> element has no such attributes
    public function testBuildFormAddsExpectedFields(): void
    {
        $this->assertSame(['title', 'description', 'class'], array_keys($this->buildAddedFields()));
    }

    // The file and its format both come from the uploaded media - asking for either again in this form would just be a second, contradictory source of truth
    public function testBuildFormDoesNotAskForTheFileOrItsFormat(): void
    {
        $added = $this->buildAddedFields();

        foreach (['src', 'type'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" comes from the uploaded media, not from the Audio form");
        }
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new AudioType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
