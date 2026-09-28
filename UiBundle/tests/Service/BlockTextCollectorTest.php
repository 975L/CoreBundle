<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockTextCollector;
use PHPUnit\Framework\TestCase;

// The texts of a set of blocks, as a page or a menu hands them to the translate command
class BlockTextCollectorTest extends TestCase
{
    /** @param array<string, mixed> $data */
    private function block(int $id, string $kind, array $data): Block
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);
        $block->setKind($kind);
        $block->setData($data);

        return $block;
    }

    /** @param list<string> $translatable */
    private function collector(array $translatable, string $label): BlockTextCollector
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getTranslatable')->willReturn($translatable);
        $registry->method('getTranslatableCollections')->willReturn([]);
        $registry->method('getLabel')->willReturn($label);

        return new BlockTextCollector($registry);
    }

    // The registry says what is translatable, and a field left empty in the writing language has no text behind it
    public function testItCollectsTheTranslatableFieldsThatHoldAText(): void
    {
        $rows = $this->collector(['title', 'text'], 'Section texte')
            ->collect([$this->block(7, 'text_section', ['title' => 'Nos bundles', 'text' => '   '])], 'Page test');

        $this->assertCount(1, $rows);
        $this->assertSame(Translation::OWNER_BLOCK, $rows[0]['owner']);
        $this->assertSame(7, $rows[0]['ownerId']);
        $this->assertSame('title', $rows[0]['field']);
        $this->assertSame('Nos bundles', $rows[0]['source']);
        $this->assertSame('Page test / Section texte', $rows[0]['label']);
    }

    // A container's slots are walked too, and a block already seen is never walked again - the guard every walk of the blocks carries
    public function testItWalksTheSlotsAndPassesTwiceOverNoBlock(): void
    {
        $slot = $this->block(2, 'card', ['title' => 'Une carte']);
        $container = $this->block(1, 'flex_columns', ['title' => 'Des colonnes']);
        $container->addSlot($slot);

        $rows = $this->collector(['title'], 'Colonnes')->collect([$container, $slot], 'Page test');

        $this->assertSame([1, 2], array_column($rows, 'ownerId'));
    }
}
