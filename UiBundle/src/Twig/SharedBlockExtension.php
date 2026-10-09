<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Repository\SharedBlockRepository;
use Twig\Attribute\AsTwigFunction;

// Draws the run of blocks a "shared_block" pointer names, each through render_block() and its own cache entry - one entry per shared block, reused by every page showing it. The pointer's own entry is tagged with them all (see BlockCacheTagResolver), so editing the shared block refreshes every page at once
class SharedBlockExtension
{
    /** @var array<string, true> the slugs being drawn right now, a shared block that ends up showing itself drawing nothing the second time */
    private array $rendering = [];

    public function __construct(
        private readonly BlockExtension $blockExtension,
        private readonly SharedBlockRepository $sharedBlockRepository,
    ) {
    }

    // Empty for an empty or unknown slug - a pointer left without its shared block renders nothing rather than a hole - and for a loop. Called from inside the pointer's own render_block(), so the html comes back raw and the outermost render lays the nonce and the links on it
    #[AsTwigFunction('render_shared_block', isSafe: ['html'])]
    public function renderSharedBlock(string $slug, bool $priority = false): string
    {
        if (isset($this->rendering[$slug])) {
            return '';
        }

        $sharedBlock = $this->sharedBlockRepository->findOneBySlug($slug);
        if (null === $sharedBlock) {
            return '';
        }

        $this->rendering[$slug] = true;

        try {
            $html = '';
            foreach ($sharedBlock->getBlocks() as $index => $block) {
                // The pointer's own priority goes to the first block only, the one standing where the pointer stands
                $html .= $this->blockExtension->renderBlock($block, priority: $priority && 0 === $index);
            }

            return $html;
        } finally {
            unset($this->rendering[$slug]);
        }
    }
}
