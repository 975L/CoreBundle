<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Where the dashboard sends someone about one config entry, and who may follow it - shared by what points at an entry from the dashboard (an alert, a feature left unused)
class ConfigEntryLink
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The admin whose screen this is, and the super-admin for a restricted entry, which stays out of the list below that role and whose link would otherwise answer 403
    public function role(Config $config): string
    {
        return true === $config->getIsRestricted()
            ? 'ROLE_SUPER_ADMIN'
            : (string) $this->configService->get('site-role-admin');
    }

    // The entry's own edit screen
    public function editUrl(Config $config): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(ConfigCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($config->getId())
            ->generateUrl();
    }
}
