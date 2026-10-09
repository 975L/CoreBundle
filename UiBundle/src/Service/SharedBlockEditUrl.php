<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Controller\Management\SharedBlockCrudController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\SharedBlockRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// A pointer's row in its page's form only holds a slug: what an editor reaching it from the page means to change is the shared block itself, on its own screen. Laid over every owner's urls by BlockEditUrlRegistry, so the "Edit this block" button lands there whatever entity carries the pointer
class SharedBlockEditUrl
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly SharedBlockRepository $sharedBlockRepository,
    ) {
    }

    // The edit urls of the pointers among these blocks, keyed by block id - none for a slug no shared block answers to, which would 404
    /**
     * @param Block[] $blocks
     *
     * @return array<int, string>
     */
    public function getEditUrls(array $blocks): array
    {
        $urls = [];
        foreach ($blocks as $block) {
            if (SharedBlock::POINTER_KIND !== $block->getKind() || null === $block->getId()) {
                continue;
            }

            $sharedBlock = $this->sharedBlockRepository->findOneBySlug((string) ($block->getData()['slug'] ?? ''));
            if (null === $sharedBlock) {
                continue;
            }

            $urls[$block->getId()] = $this->adminUrlGenerator
                ->unsetAll()
                ->setController(SharedBlockCrudController::class)
                ->setAction('edit')
                ->setEntityId($sharedBlock->getId())
                ->generateUrl();
        }

        return $urls;
    }
}
