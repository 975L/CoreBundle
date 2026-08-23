<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\FormRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// A form block's row in its owner's page form only holds the name of the form it shows: what an editor reaching it from the page itself means to change is its fields, their labels and where it posts, which live on the Form's own screen. Same rule and same place in the chain as LegalModelEditUrl, so the "Edit this block" button lands where the thing under the cursor is actually edited.
//
// Null for anything else, and for a name no Form answers to - that one would 404, so the page's own form stays the right place to fix it.
class FormEditUrl
{
    public static function build(AdminUrlGeneratorInterface $adminUrlGenerator, FormRepository $formRepository, Block $block): ?string
    {
        if ('form' !== $block->getKind()) {
            return null;
        }

        $name = (string) ($block->getData()['name'] ?? '');
        $form = '' === $name ? null : $formRepository->findOneBy(['name' => $name]);

        if (null === $form) {
            return null;
        }

        return $adminUrlGenerator
            ->unsetAll()
            ->setController(FormCrudController::class)
            ->setAction('edit')
            ->setEntityId($form->getId())
            ->generateUrl();
    }
}
