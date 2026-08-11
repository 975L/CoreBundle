<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\VideoIframeType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoIframeTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new VideoIframeType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['src', 'noCookie', 'importPoster', 'title', 'description', 'width', 'height', 'class'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the VideoIframe form");
        }
    }

    // A one-shot action, not a stored preference: VideoPosterImporter clears it once done, so it is never required
    public function testImportPosterIsAnOptionalSwitch(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['importPoster']['required']);
        $this->assertSame('checkbox-switch', $added['importPoster']['label_attr']['class']);
    }

    public function testTitleAndDescriptionAreOptional(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['title']['required']);
        $this->assertFalse($added['description']['required']);
    }

    public function testNoCookieIsOptional(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['noCookie']['required']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new VideoIframeType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
