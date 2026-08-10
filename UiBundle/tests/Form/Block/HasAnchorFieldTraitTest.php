<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class HasAnchorFieldTraitTest extends TestCase
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

        (new HasAnchorFieldTraitStub($this->anchorSlugger()))->buildForm($builder, []);

        return $added;
    }

    // Captures the SUBMIT listener and fires it with $submittedData, returning the resulting data - mirrors what happens when a "Page sections" block form is submitted (see HasAnchorFieldTrait)
    private function fireSubmit(array $submittedData, string $titleField = 'title'): array
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

        (new HasAnchorFieldTraitStub($this->anchorSlugger(), $titleField))->buildForm($builder, []);

        $form = $this->createStub(FormInterface::class);
        $event = new FormEvent($form, $submittedData);
        $listener($event);

        return $event->getData();
    }

    public function testAddAnchorFieldAddsAnOptionalAnchorField(): void
    {
        $added = $this->buildAddedFields();

        $this->assertArrayHasKey('anchor', $added);
        $this->assertFalse($added['anchor']['required']);
    }

    public function testSubmitListenerKeepsTheExplicitAnchorSlugified(): void
    {
        $data = $this->fireSubmit(['anchor' => 'Services', 'title' => 'A much longer section title']);

        $this->assertSame('services', $data['anchor']);
    }

    public function testSubmitListenerFallsBackToTheTitleFieldWhenAnchorIsEmpty(): void
    {
        $data = $this->fireSubmit(['anchor' => '', 'title' => 'Des services taillé sur mesure']);

        $this->assertSame('des-services-taille-sur-mesure', $data['anchor']);
    }

    // ContactDetailsType names "name" as its title field, the kind having no "title" of its own
    public function testSubmitListenerUsesTheConfiguredTitleFieldForTheFallback(): void
    {
        $data = $this->fireSubmit(['anchor' => '', 'heading' => 'Custom title field'], 'heading');

        $this->assertSame('custom-title-field', $data['anchor']);
    }

    public function testSubmitListenerSetsAnchorToNullWhenBothAnchorAndTitleAreEmpty(): void
    {
        $data = $this->fireSubmit(['anchor' => '', 'title' => '']);

        $this->assertNull($data['anchor']);
    }
}
