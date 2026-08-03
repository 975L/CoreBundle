<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Entity\Config;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Builds a Config's EasyAdmin edit URL, falling back to the plain list for a slug not yet in the DB
class ConfigEditUrlResolver
{
    public function __construct(private readonly AdminUrlGeneratorInterface $adminUrlGenerator)
    {
    }

    // unsetAll() first: the generator keeps its route parameters, leaking the previous entityId otherwise
    public function resolve(?Config $config): string
    {
        $urlGenerator = $this->adminUrlGenerator->unsetAll()->setController(ConfigCrudController::class);

        return $config
            ? $urlGenerator->setAction(Action::EDIT)->setEntityId($config->getId())->generateUrl()
            : $urlGenerator->setAction(Action::INDEX)->generateUrl();
    }
}
