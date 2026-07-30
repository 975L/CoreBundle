<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\TextSectionType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class TextSectionTypeTest extends TestCase
{
    private function anchorSlugger(): BlockAnchorSlugger
    {
        return new BlockAnchorSlugger(new AsciiSlugger());
    }

    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        (new TextSectionType($this->anchorSlugger()))->buildForm($builder, []);

        return $added;
    }

    // Captures the SUBMIT listener and fires it with $submittedData, returning the resulting data - mirrors what happens when a TextSection block form is submitted
    private function fireSubmit(array $submittedData): array
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

        (new TextSectionType($this->anchorSlugger()))->buildForm($builder, []);

        $form = $this->createStub(FormInterface::class);
        $event = new FormEvent($form, $submittedData);
        $listener($event);

        return $event->getData();
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['eyebrow', 'title', 'slug', 'content', 'background'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the TextSection form");
        }
    }

    public function testTitleIsOptional(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['title']['required']);
    }

    public function testEyebrowIsOptional(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['eyebrow']['required']);
    }

    public function testSubmitListenerDerivesSlugFromTitle(): void
    {
        $data = $this->fireSubmit(['title' => 'Des services taillé sur mesure']);

        $this->assertSame('des-services-taille-sur-mesure', $data['slug']);
    }

    public function testSubmitListenerSetsSlugToEmptyStringWhenTitleIsEmpty(): void
    {
        $data = $this->fireSubmit(['title' => '']);

        $this->assertSame('', $data['slug']);
    }

    // Without a title the eyebrow is the section's own heading, so the anchor follows it rather than disappearing
    public function testSubmitListenerDerivesSlugFromEyebrowWhenTitleIsEmpty(): void
    {
        $data = $this->fireSubmit(['title' => '', 'eyebrow' => 'Notre manifeste']);

        $this->assertSame('notre-manifeste', $data['slug']);
    }

    public function testSubmitListenerPrefersTitleOverEyebrowForSlug(): void
    {
        $data = $this->fireSubmit(['title' => 'Nos services', 'eyebrow' => 'Ce que nous faisons']);

        $this->assertSame('nos-services', $data['slug']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new TextSectionType($this->anchorSlugger());
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
