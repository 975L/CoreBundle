<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use c975L\UiBundle\Service\BlockCacheTagResolver;
use c975L\UiBundle\Service\BlockRenderContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

// An owner's whole run of blocks (a page, a product sheet) as one cache entry, where render_block() keeps one per block: a hit then reads neither the blocks, their medias nor their slots, the owner's own row being all the request has to load. Tagged with every block's own tags and the owner's (ownerTag()), which OwnedBlocksCacheListener empties when a block is added, removed or moved
class OwnedBlocksExtension
{
    public function __construct(
        private readonly BlockExtension $blockExtension,
        private readonly BlockCacheTagResolver $cacheTagResolver,
        private readonly BlockRepository $blockRepository,
        private readonly BlockRenderContext $renderContext,
        private readonly TagAwareCacheInterface $cache,
        private readonly RequestStack $requestStack,
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $twig,
        private readonly ?Security $security = null,
    ) {
    }

    // The owner's own tag, the one a block added, removed or moved reaches - named after its class and its id, so any HasBlocksInterface entity has one without declaring it
    public static function ownerTag(HasBlocksInterface $owner): string
    {
        $id = method_exists($owner, 'getId') ? $owner->getId() : null;

        return 'owned_blocks_' . strtolower(new \ReflectionClass($owner)->getShortName()) . '_' . $id;
    }

    // Rendered live for an editor, whose run carries the edit overlay and its urls, in a preview, and outside any request - the same cases BlockExtension::renderHtml() stores nothing for
    #[AsTwigFunction('render_owned_blocks', isSafe: ['html'])]
    public function renderOwnedBlocks(HasBlocksInterface $owner): string
    {
        $ownerTag = self::ownerTag($owner);
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || $this->renderContext->isCacheDisabled() || $this->isEditor()) {
            return $this->render($owner);
        }

        return $this->blockExtension->renderNested(fn (): string => $this->cache->get(
            $ownerTag . '_' . $request->getLocale(),
            function (ItemInterface $item, bool &$save) use ($owner, $ownerTag): string {
                $this->blockRepository->preloadTree($owner->getBlocks());

                $tags = $this->tags($owner, $ownerTag);
                if (null === $tags) {
                    $save = false;
                } else {
                    $item->expiresAfter(null);
                    $item->tag($tags);
                }

                return $this->render($owner);
            }
        ));
    }

    // Every block's own tag and whatever its kind adds, null as soon as one of them refuses to be cached - a form and its csrf token, say: the run then falls back on render_block()'s own entries, block by block
    /** @return string[]|null */
    private function tags(HasBlocksInterface $owner, string $ownerTag): ?array
    {
        $tags = [$ownerTag, BlockCacheInvalidator::CACHE_TAG_ALL];

        foreach ($owner->getBlocks() as $block) {
            if ($block->isHidden()) {
                $tags[] = 'block_' . $block->getId();

                continue;
            }

            $extra = $this->cacheTagResolver->resolve($block);
            if (null === $extra) {
                return null;
            }

            $tags = [...$tags, 'block_' . $block->getId(), ...$extra];
        }

        return array_values(array_unique($tags));
    }

    private function render(HasBlocksInterface $owner): string
    {
        return $this->twig->render('@c975LUi/components/Blocks/_owned.html.twig', ['blocks' => $owner->getBlocks()]);
    }

    private function isEditor(): bool
    {
        $role = (string) $this->configService->get('site-role-editor');

        return null !== $this->security && '' !== $role && $this->security->isGranted($role);
    }
}
