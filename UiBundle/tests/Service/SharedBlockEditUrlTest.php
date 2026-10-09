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
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\SharedBlockRepository;
use c975L\UiBundle\Service\SharedBlockEditUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class SharedBlockEditUrlTest extends TestCase
{
    private function createBlock(string $kind, array $data, ?int $id = 7): Block
    {
        $block = new Block();
        $block->setKind($kind);
        $block->setData($data);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    private function createSharedBlock(int $id): SharedBlock
    {
        $sharedBlock = new SharedBlock();
        new \ReflectionProperty(SharedBlock::class, 'id')->setValue($sharedBlock, $id);

        return $sharedBlock;
    }

    private function createService(?SharedBlock $sharedBlock): SharedBlockEditUrl
    {
        $entityId = null;
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnCallback(static function ($id) use (&$entityId, $generator) {
            $entityId = $id;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(static function () use (&$entityId): string {
            return '/management/shared-block/' . $entityId . '/edit';
        });

        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findOneBySlug')->willReturn($sharedBlock);

        return new SharedBlockEditUrl($generator, $repository);
    }

    public function testAPointerIsSentToItsSharedBlocksScreen(): void
    {
        $urls = $this->createService($this->createSharedBlock(3))
            ->getEditUrls([$this->createBlock(SharedBlock::POINTER_KIND, ['slug' => 'contact-banner'])]);

        $this->assertSame([7 => '/management/shared-block/3/edit'], $urls);
    }

    // Every other kind is edited on its owner's form, which the registry's providers answer
    public function testAnotherKindIsLeftToItsOwner(): void
    {
        $urls = $this->createService($this->createSharedBlock(3))
            ->getEditUrls([$this->createBlock('text', ['slug' => 'contact-banner'])]);

        $this->assertSame([], $urls);
    }

    // A slug naming nothing would open a 404, so the owner's form stays the right place
    public function testAPointerNamingNoSharedBlockGetsNoUrl(): void
    {
        $urls = $this->createService(null)
            ->getEditUrls([$this->createBlock(SharedBlock::POINTER_KIND, ['slug' => 'gone'])]);

        $this->assertSame([], $urls);
    }

    public function testAnUnsavedPointerGetsNoUrl(): void
    {
        $urls = $this->createService($this->createSharedBlock(3))
            ->getEditUrls([$this->createBlock(SharedBlock::POINTER_KIND, ['slug' => 'contact-banner'], null)]);

        $this->assertSame([], $urls);
    }
}
