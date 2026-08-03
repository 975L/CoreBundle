<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Util;

use c975L\UiBundle\Form\Util\CollectionReconciler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;

class CollectionReconcilerTest extends TestCase
{
    private function createItem(string $id): object
    {
        return new class ($id) {
            public function __construct(private readonly string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }
        };
    }

    // Items whose ID is missing from the submitted entries get passed to the $remove callback
    public function testPruneRemovedCallsRemoveForItemsNotInSubmittedEntries(): void
    {
        $kept = $this->createItem('1');
        $removed = $this->createItem('2');
        $removedItems = [];

        CollectionReconciler::pruneRemoved(
            [$kept, $removed],
            [['id' => '1']],
            function ($item) use (&$removedItems): void {
                $removedItems[] = $item;
            }
        );

        $this->assertSame([$removed], $removedItems);
    }

    public function testPruneRemovedCallsRemoveForNothingWhenAllItemsAreSubmitted(): void
    {
        $item1 = $this->createItem('1');
        $item2 = $this->createItem('2');
        $removedItems = [];

        CollectionReconciler::pruneRemoved(
            [$item1, $item2],
            [['id' => '1'], ['id' => '2']],
            function ($item) use (&$removedItems): void {
                $removedItems[] = $item;
            }
        );

        $this->assertSame([], $removedItems);
    }

    // Submitted entries that aren't arrays, or whose id is null/empty, are simply ignored - they can never match
    public function testPruneRemovedIgnoresMalformedSubmittedEntries(): void
    {
        $item = $this->createItem('1');
        $removedItems = [];

        CollectionReconciler::pruneRemoved(
            [$item],
            ['not-an-array', ['id' => null], ['id' => ''], ['other' => 'field']],
            function ($item) use (&$removedItems): void {
                $removedItems[] = $item;
            }
        );

        $this->assertSame([$item], $removedItems);
    }

    // A submitted entry whose id matches a surviving item is kept
    public function testDropOrphanedKeepsEntriesMatchingASurvivor(): void
    {
        $survivor = $this->createItem('1');

        $result = CollectionReconciler::dropOrphaned(
            [['id' => '1', 'label' => 'kept']],
            [$survivor],
            fn (array $entry): bool => false
        );

        $this->assertSame([['id' => '1', 'label' => 'kept']], array_values($result));
    }

    // A submitted entry whose id doesn't match any surviving item is dropped, even if $isNewEntry would accept it
    public function testDropOrphanedDropsEntriesWithIdNotMatchingAnySurvivor(): void
    {
        $survivor = $this->createItem('1');

        $result = CollectionReconciler::dropOrphaned(
            [['id' => '2', 'label' => 'ghost']],
            [$survivor],
            fn (array $entry): bool => true
        );

        $this->assertSame([], $result);
    }

    // Entries with no id (or an empty one) are new - kept only if $isNewEntry accepts them
    public function testDropOrphanedDelegatesEntriesWithoutIdToIsNewEntryCallback(): void
    {
        $result = CollectionReconciler::dropOrphaned(
            [['label' => 'brand-new'], ['id' => '', 'label' => 'also-new']],
            [],
            fn (array $entry): bool => 'brand-new' === $entry['label']
        );

        $this->assertSame([['label' => 'brand-new']], array_values($result));
    }

    // Non-array submitted entries are always dropped, without ever reaching $isNewEntry
    public function testDropOrphanedDropsNonArrayEntries(): void
    {
        $called = false;

        $result = CollectionReconciler::dropOrphaned(
            ['not-an-array'],
            [],
            function (array $entry) use (&$called): bool {
                $called = true;

                return true;
            }
        );

        $this->assertSame([], $result);
        $this->assertFalse($called);
    }

    // Shared by every entry FormType of a sortable collection (BlockType, FormFieldType, EmailBlockType)
    public function testAddIdFieldAddsAnUnmappedHiddenFieldCarryingTheGivenId(): void
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $form) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $form;
        });

        CollectionReconciler::addIdField($form, 42);

        $this->assertSame(HiddenType::class, $added['id']['type']);
        $this->assertFalse($added['id']['options']['mapped']);
        $this->assertFalse($added['id']['options']['required']);
        $this->assertSame(42, $added['id']['options']['data']);
    }

    public function testAddIdFieldCarriesNullWhenNoIdIsGiven(): void
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $form) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $form;
        });

        CollectionReconciler::addIdField($form, null);

        $this->assertNull($added['id']['options']['data']);
    }
}
