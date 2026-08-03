<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Builds an EasyAdmin edit URL for a Block's owner, optionally jumping straight to that block's own row - what every bundle attaching blocks to an entity (see HasBlocksInterface) needs to point back at them. Static and stateless rather than the trait this used to be in SiteBundle: a trait shared across bundles is only ever analysed against the users living in the same package
class BlockFocusUrl
{
    public static function build(AdminUrlGeneratorInterface $adminUrlGenerator, string $crudControllerFqcn, ?int $entityId, ?Block $block = null): string
    {
        $urlGenerator = $adminUrlGenerator
            ->unsetAll()
            ->setController($crudControllerFqcn)
            ->setAction(Action::EDIT)
            ->setEntityId($entityId);

        if (null !== $block) {
            $urlGenerator->set('focusBlock', $block->getId());
        }

        return $urlGenerator->generateUrl();
    }
}
