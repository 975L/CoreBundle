<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockOwnerRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\BlockRelocator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

// Moves a persisted Block into another collection; the normal form submission would lose its media
class BlockMoveController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name
    public const MOVE_ROUTE = 'management_ui_block_move';

    public function __construct(
        private readonly BlockRepository $blockRepository,
        private readonly BlockOwnerRegistry $blockOwnerRegistry,
        private readonly BlockRegistry $blockRegistry,
        private readonly BlockRelocator $blockRelocator,
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(
        path: '/ui/block/move',
        name: 'ui_block_move',
        options: ['methods' => ['POST']]
    )]
    public function move(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if (!$this->isCsrfTokenValid(self::MOVE_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'invalid_csrf'], 419);
        }

        $block = $this->blockRepository->find((int) $request->request->get('blockId'));
        if (!$block instanceof Block) {
            return new JsonResponse(['error' => 'unknown_block'], 404);
        }

        $ownerType = (string) $request->request->get('ownerType');
        $ownerId = (int) $request->request->get('ownerId');
        $owner = $this->blockOwnerRegistry->find($ownerType, $ownerId);
        if (!$owner instanceof HasBlocksInterface) {
            return new JsonResponse(['error' => 'unknown_owner'], 404);
        }

        if (!$this->belongsTo($block, $owner)) {
            return new JsonResponse(['error' => 'block_not_owned'], 403);
        }

        $targetContainer = null;
        $targetBlockId = $request->request->get('targetBlockId');
        if (null !== $targetBlockId && '' !== $targetBlockId) {
            $targetContainer = $this->blockRepository->find((int) $targetBlockId);
            if (!$targetContainer instanceof Block) {
                return new JsonResponse(['error' => 'unknown_target'], 404);
            }

            $error = $this->validateTarget($block, $targetContainer, $owner);
            if (null !== $error) {
                return new JsonResponse(['error' => $error], 400);
            }
        }

        $this->blockRelocator->relocate($block, $owner, $targetContainer);
        $this->entityManager->flush();

        // The flash survives the client's reload the same way it would a redirect
        $this->addFlash('success', $this->translator->trans('flash.block_moved', [], 'ui'));

        return new JsonResponse(['ok' => true]);
    }

    // Walks up to the root ancestor, bounded by nesting depth rather than by how many blocks $owner has
    private function belongsTo(Block $block, HasBlocksInterface $owner): bool
    {
        $root = $block;
        while (null !== $root->getParentBlock()) {
            $root = $root->getParentBlock();
        }

        return $owner->getBlocks()->contains($root);
    }

    // An error code, or null when valid; no cycle check needed, isAllowedInContext() making a loop structurally impossible
    private function validateTarget(Block $block, Block $target, HasBlocksInterface $owner): ?string
    {
        if ($target === $block) {
            return 'target_is_the_moved_block';
        }
        if (!$this->blockRegistry->isContainer((string) $target->getKind())) {
            return 'target_not_a_container';
        }
        if (!$this->belongsTo($target, $owner)) {
            return 'target_not_owned';
        }
        if (!$this->blockRegistry->isAllowedInContext((string) $block->getKind(), $this->blockRegistry->getSlotContext((string) $target->getKind()))) {
            return 'kind_not_allowed_in_target';
        }

        return null;
    }
}
