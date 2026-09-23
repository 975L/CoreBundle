<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\BlockCacheTagResolver;
use c975L\UiBundle\Service\BlockRenderContext;
use c975L\UiBundle\Twig\BlockExtension;
use c975L\UiBundle\Twig\OwnedBlocksExtension;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class OwnedBlocksExtensionTest extends TestCase
{
    private function owner(Block ...$blocks): HasBlocksInterface
    {
        $owner = $this->createStub(HasBlocksInterface::class);
        $owner->method('getBlocks')->willReturn(new ArrayCollection($blocks));

        return $owner;
    }

    private function block(int $id): Block
    {
        $block = new Block()->setKind('text');
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    /** @param array<int, string[]|null> $tagsById */
    private function extension(Environment $twig, TagAwareAdapter $cache, array $tagsById = [], bool $editor = false, ?BlockRenderContext $context = null): OwnedBlocksExtension
    {
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderNested')->willReturnCallback(static fn (callable $render): string => $render());

        $resolver = $this->createStub(BlockCacheTagResolver::class);
        $resolver->method('resolve')->willReturnCallback(static fn (Block $block): ?array => \array_key_exists($block->getId(), $tagsById) ? $tagsById[$block->getId()] : []);

        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($editor);

        return new OwnedBlocksExtension($blockExtension, $resolver, $this->createStub(BlockRepository::class), $context ?? new BlockRenderContext(), $cache, new RequestStack([new Request()]), $config, $twig, $security);
    }

    // A hit reads nothing: the whole run is served from the one entry, until a block of it or the owner's own tag is emptied
    public function testTheRunIsRenderedOnceUntilOneOfItsTagsIsEmptied(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(2))->method('render')->willReturn('<div class="blocks"></div>');

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $extension = $this->extension($twig, $cache, [7 => ['collection_x']]);
        $owner = $this->owner($this->block(7));

        $this->assertSame('<div class="blocks"></div>', $extension->renderOwnedBlocks($owner));
        $extension->renderOwnedBlocks($owner);

        $cache->invalidateTags(['collection_x']);
        $extension->renderOwnedBlocks($owner);
    }

    // A block moved in or out of the run only reaches the owner's tag
    public function testTheOwnersTagEmptiesTheRun(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(2))->method('render')->willReturn('');

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $extension = $this->extension($twig, $cache);
        $owner = $this->owner($this->block(7));

        $extension->renderOwnedBlocks($owner);
        $cache->invalidateTags([OwnedBlocksExtension::ownerTag($owner)]);
        $extension->renderOwnedBlocks($owner);
    }

    // One block refusing the cache (a form and its csrf token) keeps the whole run out of it
    public function testABlockRefusingTheCacheKeepsTheRunLive(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(2))->method('render')->willReturn('');

        $extension = $this->extension($twig, new TagAwareAdapter(new ArrayAdapter()), [8 => null]);
        $owner = $this->owner($this->block(7), $this->block(8));

        $extension->renderOwnedBlocks($owner);
        $extension->renderOwnedBlocks($owner);
    }

    // An editor's run carries the edit overlay and its urls, which no visitor may be served
    public function testAnEditorIsServedALiveRun(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(2))->method('render')->willReturn('');

        $extension = $this->extension($twig, new TagAwareAdapter(new ArrayAdapter()), editor: true);
        $owner = $this->owner($this->block(7));

        $extension->renderOwnedBlocks($owner);
        $extension->renderOwnedBlocks($owner);
    }

    public function testAPreviewIsRenderedLive(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(2))->method('render')->willReturn('');

        $context = new BlockRenderContext();
        $context->disableCache();
        $extension = $this->extension($twig, new TagAwareAdapter(new ArrayAdapter()), context: $context);
        $owner = $this->owner($this->block(7));

        $extension->renderOwnedBlocks($owner);
        $extension->renderOwnedBlocks($owner);
    }
}
