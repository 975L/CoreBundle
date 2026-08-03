<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Listener\BlockLabelListener;
use c975L\UiBundle\Registry\BlockRegistry;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

class BlockLabelListenerTest extends TestCase
{
    private function createArgs(object $entity): PostLoadEventArgs
    {
        return new PostLoadEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    public function testPostLoadSetsTheTranslatedLabelForAKnownKind(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getLabel')->willReturn('Article');

        $block = new Block();
        $block->setKind('article');

        (new BlockLabelListener($registry))->postLoad($this->createArgs($block));

        $this->assertSame('Article', $block->getLabel());
    }

    public function testPostLoadIgnoresEntitiesThatAreNotBlocks(): void
    {
        $registry = $this->createStub(BlockRegistry::class);

        (new BlockLabelListener($registry))->postLoad($this->createArgs(new \stdClass()));

        $this->addToAssertionCount(1);
    }

    public function testPostLoadIgnoresBlocksWithNoKind(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->never())->method('has');

        (new BlockLabelListener($registry))->postLoad($this->createArgs(new Block()));

        $this->addToAssertionCount(1);
    }

    public function testPostLoadIgnoresUnknownKinds(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(false);

        $block = new Block();
        $block->setKind('unknown');

        (new BlockLabelListener($registry))->postLoad($this->createArgs($block));

        $this->assertNull($block->getLabel());
    }
}
