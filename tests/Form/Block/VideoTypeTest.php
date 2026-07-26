<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\VideoType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        (new VideoType())->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['options', 'width', 'height'], array_keys($added));
    }

    // The video file, its format and its cover image all come from the block's own medias now - asking for any of them again in this form would just be a second, contradictory source of truth
    public function testBuildFormDoesNotAskForTheFileFormatOrPoster(): void
    {
        $added = $this->buildAddedFields();

        foreach (['src', 'type', 'poster'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" comes from the uploaded media, not from the Video form");
        }
    }

    // Autoplay/muted/loop used to be three separate checkboxes - they're a single multi-select now, same widget as the css classes of the other blocks
    public function testPlaybackOptionsAreASingleOptionalMultiSelect(): void
    {
        $options = $this->buildAddedFields()['options'];

        $this->assertTrue($options['multiple']);
        $this->assertFalse($options['expanded']);
        $this->assertFalse($options['required']);
        $this->assertSame(['autoplay', 'muted', 'loop'], array_values($options['choices']));
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new VideoType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
