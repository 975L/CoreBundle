<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\VideoGridType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class VideoGridTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        (new VideoGridType(new BlockAnchorSlugger(new AsciiSlugger())))->buildForm($builder, []);

        return $added;
    }

    // The section head comes from AbstractSectionHeadContainerType, the link pair from this type itself
    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'linkLabel', 'linkUrl', 'anchor'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the VideoGrid form");
        }
    }

    // Nothing about a video is entered here - the videos are the block's slots, each a full "video_iframe"/"video" block
    public function testNoVideoFieldIsAdded(): void
    {
        $added = $this->buildAddedFields();

        foreach (['src', 'importPoster', 'medias', 'slots'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" belongs to a slot, not to the VideoGrid form");
        }
    }

    // Every field is optional: a grid with no head at all is a valid row of players
    public function testNoFieldIsRequired(): void
    {
        $added = $this->buildAddedFields();

        foreach ($added as $field => $options) {
            $this->assertFalse($options['required'], "\"$field\" should not be required");
        }
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new VideoGridType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
