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
use c975L\UiBundle\Form\AnimationChoiceType;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// A container's slots are cascade-removed and orphan-removed, so anything that reads them as "gone" deletes them for good. An absent "slots" key used to be read exactly that way, whatever had made it absent - which is how a live page lost the cards of a section_cards block. Driven over a real form, not over a stub of it, because the deletion happens inside Symfony rather than in BlockType: PRE_SET_DATA has already declared the "slots" child, and a declared child whose key is absent is submitted with null, which CollectionType's own resize listener reads as "every row removed". Skipping BlockType's pruning keeps nothing; only taking the child off the form does.
class BlockSlotsGuardTest extends TestCase
{
    public function testSlotsSurviveASubmissionThatNeverCarriedTheCollection(): void
    {
        $container = $this->containerWithSlots(2);

        $form = $this->submit($container, ['kind' => 'section_cards']);

        $this->assertCount(2, $container->getSlots(), 'A submission carrying neither the marker nor the collection emptied the container: an editor who never saw the slots deleted them.');
        $this->assertFalse($form->has('slots'), 'The collection was left declared on the form, where Symfony feeds it the absent key as a null and CollectionType empties it.');
    }

    // The other half of the guard: the marker is what says the collection really was on screen and really was emptied
    public function testTheMarkerLetsAGenuineEmptyingThrough(): void
    {
        $container = $this->containerWithSlots(2);

        $this->submit($container, ['kind' => 'section_cards', BlockType::SLOTS_RENDERED => '1']);

        $this->assertCount(0, $container->getSlots());
    }

    // An edit page opened before the marker existed still carries its slots, and has to go on saving normally
    public function testTheSubmittedCollectionAloneIsProofEnough(): void
    {
        $container = $this->containerWithSlots(2);
        $kept = $container->getSlots()->first();

        $this->submit($container, ['kind' => 'section_cards', 'slots' => [['id' => (string) $kept->getId(), 'kind' => 'card']]]);

        $this->assertSame([$kept], array_values($container->getSlots()->toArray()));
    }

    // PHP cuts the body at max_input_vars wherever it lands - possibly between the marker and the collection it vouches for, which is why the marker alone is not enough
    public function testATruncatedSubmissionRemovesNothing(): void
    {
        $container = $this->containerWithSlots(2);

        $this->submit($container, ['kind' => 'section_cards', BlockType::SLOTS_RENDERED => '1'], truncated: true);

        $this->assertCount(2, $container->getSlots());
    }

    // A body cut mid-collection carries some of its rows: those go off the form with it, rather than being read as the only ones left
    public function testATruncatedSubmissionKeepsTheRowsItNeverCarried(): void
    {
        $container = $this->containerWithSlots(2);
        $submitted = ['kind' => 'section_cards', BlockType::SLOTS_RENDERED => '1', 'slots' => [['id' => '1', 'kind' => 'card']]];

        $form = $this->submit($container, $submitted, truncated: true);

        $this->assertCount(2, $container->getSlots());
        $this->assertArrayNotHasKey('slots', $form->getExtraData(), 'The partial rows were left in the submission, where a field no longer claiming them fails the whole form as an extra one.');
    }

    // Whatever the reason, the marker itself stays claimed by a field: a key no field claims fails the whole form as an extra one
    public function testTheMarkerIsNeverLeftAsAnExtraField(): void
    {
        $form = $this->submit($this->containerWithSlots(2), ['kind' => 'section_cards', BlockType::SLOTS_RENDERED => '1'], truncated: true);

        $this->assertSame([], $form->getExtraData());
    }

    private function submit(Block $container, array $submitted, bool $truncated = false): FormInterface
    {
        $form = $this->factory($truncated)->create(BlockType::class, $container, ['context' => null]);
        $form->submit($submitted);

        return $form;
    }

    // The real form factory, holding BlockType itself: the type is what is under test, but the deletion it guards against is Symfony's own doing
    private function factory(bool $truncated): FormFactoryInterface
    {
        $type = new BlockType($this->registry(), $this->router(), $this->requestStack($truncated));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type, new AnimationChoiceType()], []))
            ->getFormFactory();
    }

    // Ids assigned by hand: pruneRemoved() reconciles on them, and nothing here goes near a database
    private function containerWithSlots(int $count): Block
    {
        $container = new Block();
        $container->setKind('section_cards');

        for ($i = 1; $i <= $count; ++$i) {
            $slot = new Block();
            $slot->setKind('card');
            $this->setId($slot, $i);
            $container->addSlot($slot);
        }

        return $container;
    }

    private function setId(Block $block, int $id): void
    {
        $property = new \ReflectionProperty(Block::class, 'id');
        $property->setValue($block, $id);
    }

    // "data" is declared as a bare FormType: what the kind's own sub-form holds has nothing to do with the slots guard, and CardType would drag its own field types in
    private function registry(): BlockRegistry
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isContainer')->willReturnCallback(static fn (string $kind): bool => 'section_cards' === $kind);
        $registry->method('hasMediaTypes')->willReturn(false);
        $registry->method('getFormClass')->willReturn(FormType::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $registry->method('isAllowedInContext')->willReturn(true);
        $registry->method('groupedByCategory')->willReturn(['Sections' => ['Cards' => 'section_cards', 'Card' => 'card']]);

        return $registry;
    }

    private function router(): UrlGeneratorInterface
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/ui/block/data-form');

        return $router;
    }

    // A body already at the limit is what a truncated one looks like from PHP's side: everything past it is simply not there
    private function requestStack(bool $truncated): RequestStack
    {
        $stack = new RequestStack();
        $limit = (int) ini_get('max_input_vars');
        $stack->push(new Request(request: $truncated ? array_fill(0, max($limit, 1), 'x') : ['title' => 'Home']));

        return $stack;
    }
}
