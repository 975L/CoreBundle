<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\SharedBlockRepository;
use c975L\UiBundle\Twig\BlockExtension;
use c975L\UiBundle\Twig\SharedBlockExtension;
use PHPUnit\Framework\TestCase;

// What a "shared_block" pointer draws: the run it names, block by block, and nothing at all when there is nothing sound to draw
class SharedBlockExtensionTest extends TestCase
{
    // Each block of the run through render_block(), so each keeps its own cache entry shared by every page; the pointer's priority goes to the first one only
    public function testTheRunIsDrawnBlockByBlockWithThePriorityOnTheFirstOnly(): void
    {
        $first = new Block()->setKind('cta_band');
        $second = new Block()->setKind('article');
        $shared = new SharedBlock()->setSlug('contact')->addBlock($first)->addBlock($second);

        $calls = [];
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(static function (Block $block, ?string $cacheKey = null, array $cacheTags = [], bool $priority = false) use (&$calls): string {
            $calls[] = [$block->getKind(), $priority];

            return '<' . $block->getKind() . '>';
        });

        $html = new SharedBlockExtension($blockExtension, $this->createRepository(['contact' => $shared]))->renderSharedBlock('contact', true);

        $this->assertSame('<cta_band><article>', $html);
        $this->assertSame([['cta_band', true], ['article', false]], $calls);
    }

    // A slug no shared block answers to - deleted elsewhere, not imported yet - renders nothing rather than a hole
    public function testAnUnknownOrEmptySlugRendersNothing(): void
    {
        $extension = new SharedBlockExtension($this->createStub(BlockExtension::class), $this->createRepository([]));

        $this->assertSame('', $extension->renderSharedBlock('nowhere'));
        $this->assertSame('', $extension->renderSharedBlock(''));
    }

    // A shared block showing itself draws its run once, the pointer met again inside it drawing nothing
    public function testAPointerLoopIsDrawnOnce(): void
    {
        $pointer = new Block()->setKind(SharedBlock::POINTER_KIND)->setData(['slug' => 'loop']);
        $shared = new SharedBlock()->setSlug('loop')->addBlock($pointer);

        $extension = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(static function (Block $block) use (&$extension): string {
            return '[' . $extension->renderSharedBlock((string) $block->getData()['slug']) . ']';
        });
        $extension = new SharedBlockExtension($blockExtension, $this->createRepository(['loop' => $shared]));

        $this->assertSame('[]', $extension->renderSharedBlock('loop'));
    }

    // A block failing to render leaves the slug free again, the next pointer on the page drawing it normally
    public function testTheSlugIsReleasedAfterAFailure(): void
    {
        $shared = new SharedBlock()->setSlug('contact')->addBlock(new Block()->setKind('cta_band'));

        $failing = true;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(static function () use (&$failing): string {
            if ($failing) {
                throw new \RuntimeException('Broken template');
            }

            return '<cta_band>';
        });
        $extension = new SharedBlockExtension($blockExtension, $this->createRepository(['contact' => $shared]));

        try {
            $extension->renderSharedBlock('contact');
        } catch (\RuntimeException) {
        }
        $failing = false;

        $this->assertSame('<cta_band>', $extension->renderSharedBlock('contact'));
    }

    /** @param array<string, SharedBlock> $sharedBlocks */
    private function createRepository(array $sharedBlocks): SharedBlockRepository
    {
        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(static fn (string $slug): ?SharedBlock => $sharedBlocks[$slug] ?? null);

        return $repository;
    }
}
