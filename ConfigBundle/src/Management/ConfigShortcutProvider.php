<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Controller\Management\ConfigPruneController;
use c975L\ConfigBundle\Controller\Management\ConfigShortcutController;
use c975L\ConfigBundle\Controller\Management\MaintenanceShortcutController;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UserFormSeeder;
use c975L\UiBundle\Repository\FormRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

// To add a ShortcutProvider, you need to: add the Management Folder in the src/ folder of your bundle; create a class implementing ShortcutProviderInterface, providing a getShortcuts() method (label already translated, like AlertProviderInterface); each shortcut's 'route' must accept a POST request and check its own CSRF token (see ConfigShortcutController); add the declaration of the Management folder in the services.yaml file of your bundle; ConfigBundle will automatically detect the ShortcutProvider and add it to the dashboard

class ConfigShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
        private readonly FormRepository $formRepository,
    ) {
    }

    // A declaration of shortcuts, one entry each: its length says how many the dashboard offers
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function getShortcuts(): array
    {
        $maintenanceEnabled = (bool) $this->configService->get('site-maintenance');
        // Found by its action, the one thing an admin cannot rename from the back-office - a form renamed there would otherwise take this tile away with it, while the site kept registering people (see RegistrationStatusProvider, which reads the same field)
        $registerForm = $this->formRepository->findOneBy(['action' => UserFormSeeder::REGISTER_ACTION]);

        return [
            [
                'label' => $this->translator->trans('label.config_clear_cache', [], 'config'),
                'icon' => 'fa fa-broom',
                'route' => ConfigShortcutController::CLEAR_CACHE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_MAINTENANCE,
            ],
            [
                'label' => $this->translator->trans('label.config_export_sql', [], 'config'),
                'icon' => 'fas fa-database',
                'route' => ConfigShortcutController::EXPORT_SQL_ROUTE,
                'active' => false,
                'role' => $this->configService->get('site-role-admin'),
                'category' => ShortcutProviderInterface::CATEGORY_EXPORT,
            ],
            [
                'label' => $this->translator->trans('label.config_export_sync_all', [], 'config'),
                'icon' => 'fas fa-cloud-download-alt',
                'route' => ConfigShortcutController::EXPORT_SYNC_ALL_ROUTE,
                'active' => false,
                'role' => $this->configService->get('site-role-admin'),
                'category' => ShortcutProviderInterface::CATEGORY_EXPORT,
            ],
            [
                'label' => $this->translator->trans('label.config_prune', [], 'config'),
                'icon' => 'fas fa-trash-alt',
                'route' => ConfigPruneController::INDEX_ROUTE,
                // Opens the listing page rather than deleting anything, hence the only GET tile so far (see ShortcutProviderInterface)
                'method' => 'GET',
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_MAINTENANCE,
            ],
            [
                'label' => $this->translator->trans('label.config_sitemaps_create', [], 'config'),
                'icon' => 'fas fa-sitemap',
                'route' => ConfigShortcutController::SITEMAPS_CREATE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_SITE,
            ],
            [
                'label' => $this->translator->trans('label.config_seo_files_create', [], 'config'),
                'icon' => 'fas fa-robot',
                'route' => ConfigShortcutController::SEO_FILES_CREATE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_SITE,
            ],
            [
                'label' => $this->translator->trans('label.config_seo_crawlers_update', [], 'config'),
                'icon' => 'fas fa-user-secret',
                'route' => ConfigShortcutController::SEO_CRAWLERS_UPDATE_ROUTE,
                // A one-shot regeneration, not a toggle: whether the site blocks AI crawlers is read from the settings screen, not from a tile the template would paint as a warning (see ShortcutProviderInterface)
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_SITE,
            ],
            [
                'label' => $this->translator->trans('label.export_tables', [], 'config'),
                'icon' => 'fas fa-database',
                'route' => ConfigShortcutController::EXPORT_TABLES_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_EXPORT,
            ],
            [
                'label' => $this->translator->trans(
                    null !== $registerForm && $registerForm->isEnabled() ? 'label.user_registration_disable' : 'label.user_registration_enable',
                    [],
                    'config',
                ),
                'icon' => 'fas fa-user-plus',
                'route' => ConfigShortcutController::REGISTRATION_ENABLED_TOGGLE_ROUTE,
                'active' => null !== $registerForm && $registerForm->isEnabled(),
                // The one toggle whose "off" is the state to signal: a site letting nobody register (or whose form was never seeded) looks exactly like one that works, hence the flag reversed here rather than inherited from 'active' (see ShortcutProviderInterface)
                'warning' => null === $registerForm || !$registerForm->isEnabled(),
                'role' => $this->configService->get('site-role-admin'),
                'category' => ShortcutProviderInterface::CATEGORY_TOGGLE,
            ],
            [
                'label' => $this->translator->trans(
                    $maintenanceEnabled ? 'label.maintenance_disable' : 'label.maintenance_enable',
                    [],
                    'config',
                ),
                'icon' => 'fa fa-wrench',
                'route' => MaintenanceShortcutController::TOGGLE_ROUTE_MAINTENANCE,
                'active' => $maintenanceEnabled,
                'role' => $this->configService->get('site-role-admin'),
                'category' => ShortcutProviderInterface::CATEGORY_TOGGLE,
            ],
        ];
    }
}
