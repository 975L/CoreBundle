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
use c975L\UiBundle\Listener\BlockVideoNoCookieListener;
use c975L\UiBundle\Twig\VideoExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

class BlockVideoNoCookieListenerTest extends TestCase
{
    private function createPersistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    private function createUpdateArgs(object $entity, EntityManagerInterface $entityManager): PreUpdateEventArgs
    {
        $changeSet = [];

        return new PreUpdateEventArgs($entity, $entityManager, $changeSet);
    }

    private function videoIframeBlock(array $data): Block
    {
        return (new Block())->setKind('video_iframe')->setData($data);
    }

    public function testPrePersistIgnoresEntitiesThatAreNotBlock(): void
    {
        $listener = new BlockVideoNoCookieListener(new VideoExtension());

        $listener->prePersist($this->createPersistArgs(new \stdClass()));

        $this->addToAssertionCount(1);
    }

    public function testPrePersistIgnoresBlocksOfOtherKinds(): void
    {
        $block = (new Block())->setKind('video')->setData(['src' => 'https://youtu.be/abc123', 'noCookie' => true]);

        (new BlockVideoNoCookieListener(new VideoExtension()))->prePersist($this->createPersistArgs($block));

        $this->assertSame('https://youtu.be/abc123', $block->getData()['src']);
    }

    public function testPrePersistLeavesSrcUntouchedWhenNoCookieIsUnchecked(): void
    {
        $block = $this->videoIframeBlock(['src' => 'https://youtu.be/abc123', 'noCookie' => false]);

        (new BlockVideoNoCookieListener(new VideoExtension()))->prePersist($this->createPersistArgs($block));

        $this->assertSame('https://youtu.be/abc123', $block->getData()['src']);
    }

    public function testPrePersistRewritesSrcToNoCookieHostWhenChecked(): void
    {
        $block = $this->videoIframeBlock(['src' => 'https://youtu.be/abc123', 'noCookie' => true]);

        (new BlockVideoNoCookieListener(new VideoExtension()))->prePersist($this->createPersistArgs($block));

        $this->assertSame('https://www.youtube-nocookie.com/embed/abc123', $block->getData()['src']);
    }

    public function testPrePersistLeavesNonYoutubeSrcUntouchedEvenWhenChecked(): void
    {
        $block = $this->videoIframeBlock(['src' => 'https://player.vimeo.com/video/123456', 'noCookie' => true]);

        (new BlockVideoNoCookieListener(new VideoExtension()))->prePersist($this->createPersistArgs($block));

        $this->assertSame('https://player.vimeo.com/video/123456', $block->getData()['src']);
    }

    public function testPreUpdateIgnoresEntitiesThatAreNotBlock(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getUnitOfWork');

        (new BlockVideoNoCookieListener(new VideoExtension()))
            ->preUpdate($this->createUpdateArgs(new \stdClass(), $entityManager));

        $this->addToAssertionCount(1);
    }

    public function testPreUpdateDoesNotRecomputeChangeSetWhenNoCookieIsUnchecked(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getUnitOfWork');

        $block = $this->videoIframeBlock(['src' => 'https://youtu.be/abc123', 'noCookie' => false]);

        (new BlockVideoNoCookieListener(new VideoExtension()))
            ->preUpdate($this->createUpdateArgs($block, $entityManager));

        $this->assertSame('https://youtu.be/abc123', $block->getData()['src']);
    }

    public function testPreUpdateRewritesSrcAndRecomputesTheChangeSet(): void
    {
        $classMetadata = new ClassMetadata(Block::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())
            ->method('recomputeSingleEntityChangeSet')
            ->with($classMetadata, $this->isInstanceOf(Block::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('getClassMetadata')->with(Block::class)->willReturn($classMetadata);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $block = $this->videoIframeBlock(['src' => 'https://youtu.be/abc123', 'noCookie' => true]);

        (new BlockVideoNoCookieListener(new VideoExtension()))
            ->preUpdate($this->createUpdateArgs($block, $entityManager));

        $this->assertSame('https://www.youtube-nocookie.com/embed/abc123', $block->getData()['src']);
    }
}
