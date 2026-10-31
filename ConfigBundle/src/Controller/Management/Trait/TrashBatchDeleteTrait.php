<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management\Trait;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Symfony\Component\HttpFoundation\Response;

// The batch "Delete" of a recycle bin, the same on every screen holding one (see EasyAdminActionHelper::trashBatchDelete(), which posts it with "trash=1"): the admin role checked before the first row is touched, as EasyAdmin only checks the editor's one of Action::DELETE and a refusal met row by row would leave the selection half removed, then a single message for the whole selection rather than one per row. The using controller holds $configService and $translator, and its deleteEntity() removes a row of the bin for good
trait TrashBatchDeleteTrait
{
    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        // From the list the batch only moves rows to the bin, which deleteEntity() does at the screen's own role
        if (!$context->getRequest()->query->getBoolean('trash')) {
            return parent::batchDelete($context, $batchActionDto);
        }

        $this->denyAccessUnlessGranted((string) $this->configService->get('site-role-admin'));

        $ids = $batchActionDto->getEntityIds();
        $repository = $this->container->get('doctrine')->getManagerForClass($batchActionDto->getEntityFqcn())->getRepository($batchActionDto->getEntityFqcn());
        $response = parent::batchDelete($context, $batchActionDto);

        // Counted on what is actually gone, a stale selection holding a row restored meanwhile being skipped by deleteEntity()
        $removed = \count($ids) - \count($repository->findBy(['id' => $ids]));
        if ($removed > 0) {
            $this->addFlash('success', $this->translator->trans('flash.trash_removed_for_good', ['%count%' => $removed], 'config'));
        }

        return $response;
    }
}
