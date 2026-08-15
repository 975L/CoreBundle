<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\AnimationChoiceType;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Picking another kind rebuilds the "data" sub-form client-side and nothing else, so everything the previous kind rendered around it - its medias' own metadata fields, its slots - is still posted. Left in the submission, a key no field claims fails the whole form with "This form should not contain extra fields": the save only went through on a second try, by which point the browser had re-rendered the form for the new kind and dropped what the first one carried.
class BlockKindSwitchTest extends TestCase
{
    // The block keeps the medias/slots themselves, so switching back brings them straight back
    public function testTheSlotsOfTheContainerLeftBehindAreNoExtraFields(): void
    {
        $container = $this->containerWithSlot();

        $form = $this->submit($container, [
            'kind' => 'text',
            'slots' => [['id' => '1', 'kind' => 'card']],
            BlockType::SLOTS_RENDERED => '1',
        ]);

        $this->assertSame([], $form->getExtraData());
        $this->assertCount(1, $container->getSlots(), 'Switching away from a container deleted its slots, cascade-removed and orphan-removed.');
        $this->assertFalse($form->has('slots'), 'The collection was left declared on the form, where Symfony feeds it the absent key as a null and CollectionType empties it.');
    }

    // Same for the medias of a kind switched to one that renders none
    public function testTheMediasOfTheKindLeftBehindAreNoExtraFields(): void
    {
        $block = new Block()->setKind('article')->addMedia(new Media()->setLabel('A caption'));

        $form = $this->submit($block, [
            'kind' => 'text',
            'medias' => [['id' => '1', 'position' => '0', 'label' => 'A caption']],
            'mediaUpload' => '',
        ]);

        $this->assertSame([], $form->getExtraData());
        $this->assertCount(1, $block->getMedias());
        $this->assertFalse($form->has('medias'));
    }

    // Nothing changed hands: a plain save of an unchanged kind carries no leftovers, and must not have its own submitted keys taken away
    public function testASubmissionKeepingItsKindIsLeftAlone(): void
    {
        $container = $this->containerWithSlot();
        $kept = $container->getSlots()->first();

        $form = $this->submit($container, [
            'kind' => 'section_cards',
            'slots' => [['id' => '1', 'kind' => 'card']],
            BlockType::SLOTS_RENDERED => '1',
        ]);

        $this->assertTrue($form->has('slots'));
        $this->assertSame([$kept], array_values($container->getSlots()->toArray()));
    }

    // Each kind builds its media rows with its own set of fields (a card's teaser image has no caption, no dimensions, no credits - see MediaUploadType), so the rows left behind carry keys the new kind never declares. Driven over a stand-in entry type rather than MediaUploadType itself, whose Vich field types can't be built outside a container: what is under test is that the collection's own prototype is what decides, whichever entry type it was built from
    public function testTheSubmittedRowsAreCutDownToWhatTheEntryFormDeclares(): void
    {
        $collection = Forms::createFormFactory()->create(CollectionType::class, null, [
            'entry_type' => TeaserEntryType::class,
            'allow_add' => true,
            'prototype' => true,
        ]);

        $entries = $this->dropForeignEntryKeys([
            ['id' => '1', 'position' => '0', 'label' => 'A caption', 'width' => '300', 'credits' => 'Someone'],
        ], $collection);

        $this->assertSame([['id' => '1', 'position' => '0']], $entries);
    }

    // A collection built without a prototype has nothing to read the declared fields off - the submission goes through untouched rather than being emptied
    public function testTheSubmittedRowsAreLeftAloneWithoutAPrototype(): void
    {
        $collection = Forms::createFormFactory()->create(CollectionType::class, null, [
            'entry_type' => TeaserEntryType::class,
            'allow_add' => true,
            'prototype' => false,
        ]);

        $entries = $this->dropForeignEntryKeys([['id' => '1', 'label' => 'A caption']], $collection);

        $this->assertSame([['id' => '1', 'label' => 'A caption']], $entries);
    }

    private function dropForeignEntryKeys(array $entries, FormInterface $collection): array
    {
        $method = new \ReflectionMethod(BlockType::class, 'dropForeignEntryKeys');

        return $method->invoke(new BlockType($this->registry(), $this->router()), $entries, $collection);
    }

    private function submit(Block $block, array $submitted): FormInterface
    {
        $form = $this->factory()->create(BlockType::class, $block, ['context' => null]);
        $form->submit($submitted);

        return $form;
    }

    // Id assigned by hand: the submitted rows are reconciled on it, and nothing here goes near a database
    private function containerWithSlot(): Block
    {
        $slot = new Block()->setKind('card');
        new \ReflectionProperty(Block::class, 'id')->setValue($slot, 1);

        return new Block()->setKind('section_cards')->addSlot($slot);
    }

    private function factory(): FormFactoryInterface
    {
        $type = new BlockType($this->registry(), $this->router(), new RequestStack([new Request(request: ['kind' => 'text'])]));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type, new AnimationChoiceType()], []))
            ->getFormFactory();
    }

    // "data" is declared as a bare FormType: what a kind's own sub-form holds has nothing to do with the leftovers around it. "text" is the kind with neither medias nor slots - and none of the kinds here declares media types, MediaUploadType's Vich field types not being buildable outside a container (see testTheSubmittedRowsAreCutDownToWhatTheEntryFormDeclares for that half)
    private function registry(): BlockRegistry
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isContainer')->willReturnCallback(static fn (string $kind): bool => 'section_cards' === $kind);
        $registry->method('hasMediaTypes')->willReturn(false);
        $registry->method('getFormClass')->willReturn(FormType::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $registry->method('isAllowedInContext')->willReturn(true);
        $registry->method('groupedByCategory')->willReturn(['Elements' => ['Article' => 'article', 'Text' => 'text', 'Card' => 'card', 'Cards' => 'section_cards']]);

        return $registry;
    }

    private function router(): UrlGeneratorInterface
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/ui/block/data-form');

        return $router;
    }
}

// Stands in for MediaUploadType's own entry form: a couple of fields every kind declares, and none of the per-image metadata only some of them do
class TeaserEntryType extends \Symfony\Component\Form\AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('position', TextType::class);
    }
}
