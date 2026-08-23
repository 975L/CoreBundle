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
use c975L\ConfigBundle\Controller\Management\NotFoundCrudController;
use c975L\ConfigBundle\Controller\Management\UrlMetadataCrudController;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's own guided projects, running the 1000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. Each carries the role its own screen is gated by, so a parcours is never offered to someone its very first step turns away - the dashboard the list is started from opens to an editor (see DashboardController::index()), and three of these five walk a screen only an admin may read. Only the opening step of each carries an url: from there the parcours walks the screen the user has been sent to, highlighting the button or the field they are meant to use next - one they click themselves, which brings the panel back on that very step (see assets/js/guided-project.js resume())
class ConfigGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            $this->settingsProject(),
            $this->healthCheckProject(),
            $this->maintenanceProject(),
            $this->notFoundProject(),
            $this->urlMetadataProject(),
        ];
    }

    // A link that leads nowhere is reported by nobody and answered by nothing until someone turns it into a redirect - the one screen where the two ends of that job meet
    private function notFoundProject(): array
    {
        return [
            'slug' => 'config-not-found',
            'label' => 'label.guided_project_config_not_found',
            'description' => 'description.guided_project_config_not_found',
            'translation_domain' => 'config',
            // Slipped between the maintenance rehearsal and the url metadata, next to the redirects this walks to rather than appended after the last of them
            'order' => 1040,
            // The bar NotFoundCrudController sets on its own index and on its "createRedirect", and the one RedirectCrudController sets on the "new" this ends up on
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_config_not_found_open',
                    'description' => 'description.guided_step_config_not_found_open',
                    'url' => $this->indexUrl(NotFoundCrudController::class),
                ],
                [
                    // Sorted by "lastSeen" descending (see NotFoundCrudController::configureCrud()), so the first row is the link that broke most recently
                    'label' => 'label.guided_step_config_not_found_row',
                    'description' => 'description.guided_step_config_not_found_row',
                    'highlight' => 'table tbody tr:first-child',
                ],
                [
                    // A custom action, so EasyAdmin names its button after it just the same - it opens RedirectCrudController's "new" with the dead path already set (see its createEntity())
                    'label' => 'label.guided_step_config_not_found_create',
                    'description' => 'description.guided_step_config_not_found_create',
                    'highlight' => '.action-createRedirect',
                ],
                [
                    'label' => 'label.guided_step_config_not_found_from',
                    'description' => 'description.guided_step_config_not_found_from',
                    'highlight' => '#Redirect_fromPath',
                ],
                [
                    'label' => 'label.guided_step_config_not_found_to',
                    'description' => 'description.guided_step_config_not_found_to',
                    'highlight' => '#Redirect_toUrl',
                ],
                [
                    'label' => 'label.guided_step_config_not_found_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_config_not_found_done',
                    'description' => 'description.guided_step_config_not_found_done',
                ],
            ],
        ];
    }

    // Where every setting of every bundle lives, which is the first thing to find on a back office one doesn't know
    private function settingsProject(): array
    {
        return [
            'slug' => 'config-settings',
            'label' => 'label.guided_project_config_settings',
            'description' => 'description.guided_project_config_settings',
            'translation_domain' => 'config',
            'order' => 1010,
            // The bar ConfigCrudController sets on its own index and edit
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_config_settings_open',
                    'description' => 'description.guided_step_config_settings_open',
                    'url' => $this->indexUrl(ConfigCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_config_settings_group',
                    'description' => 'description.guided_step_config_settings_group',
                    'highlight' => 'table tbody tr:first-child a',
                ],
                [
                    'label' => 'label.guided_step_config_settings_entry',
                    'description' => 'description.guided_step_config_settings_entry',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_config_settings_value',
                    'description' => 'description.guided_step_config_settings_value',
                    'highlight' => '#Config_value',
                ],
                [
                    'label' => 'label.guided_step_config_settings_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_config_settings_alerts',
                    'description' => 'description.guided_step_config_settings_alerts',
                ],
            ],
        ];
    }

    // What the site answers to a visitor, checked from the outside rather than taken on trust
    private function healthCheckProject(): array
    {
        return [
            'slug' => 'config-health-check',
            'label' => 'label.guided_project_config_health_check',
            'description' => 'description.guided_project_config_health_check',
            'translation_domain' => 'config',
            'order' => 1020,
            // The bar every action of HealthCheckController sets on itself
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_config_health_check_open',
                    'description' => 'description.guided_step_config_health_check_open',
                    'url' => $this->urlGenerator->generate('management_health_check_index'),
                ],
                [
                    'label' => 'label.guided_step_config_health_check_run',
                    'description' => 'description.guided_step_config_health_check_run',
                    'highlight' => 'form[action$="/health-check/run"] button',
                ],
                [
                    'label' => 'label.guided_step_config_health_check_read',
                    'description' => 'description.guided_step_config_health_check_read',
                    'highlight' => '[data-controller="health-check-table"]',
                ],
                [
                    'label' => 'label.guided_step_config_health_check_fix',
                    'description' => 'description.guided_step_config_health_check_fix',
                ],
                [
                    'label' => 'label.guided_step_config_health_check_again',
                    'description' => 'description.guided_step_config_health_check_again',
                ],
            ],
        ];
    }

    // Rehearsed on a quiet day rather than discovered on the day it's needed
    private function maintenanceProject(): array
    {
        return [
            'slug' => 'config-maintenance',
            'label' => 'label.guided_project_config_maintenance',
            'description' => 'description.guided_project_config_maintenance',
            'translation_domain' => 'config',
            'order' => 1030,
            // The bar the maintenance toggle shortcut declares, and the settings screen it links back to
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_config_maintenance_open',
                    'description' => 'description.guided_step_config_maintenance_open',
                    'url' => $this->urlGenerator->generate('management'),
                ],
                [
                    'label' => 'label.guided_step_config_maintenance_enable',
                    'description' => 'description.guided_step_config_maintenance_enable',
                    'highlight' => 'form[action$="/config/maintenance-toggle"] button',
                ],
                [
                    'label' => 'label.guided_step_config_maintenance_check',
                    'description' => 'description.guided_step_config_maintenance_check',
                ],
                [
                    'label' => 'label.guided_step_config_maintenance_disable',
                    'description' => 'description.guided_step_config_maintenance_disable',
                    'highlight' => 'form[action$="/config/maintenance-toggle"] button',
                ],
                [
                    'label' => 'label.guided_step_config_maintenance_done',
                    'description' => 'description.guided_step_config_maintenance_done',
                ],
            ],
        ];
    }

    // The listings no entity carries speak for themselves nowhere else: left empty, a search result or a shared link shows the url and nothing more
    private function urlMetadataProject(): array
    {
        return [
            'slug' => 'config-url-metadata',
            'label' => 'label.guided_project_config_url_metadata',
            'description' => 'description.guided_project_config_url_metadata',
            'translation_domain' => 'config',
            'order' => 1050,
            // The bar UrlMetadataCrudController sets on its own index and edit, this one being the editor's
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_config_url_metadata_open',
                    'description' => 'description.guided_step_config_url_metadata_open',
                    'url' => $this->indexUrl(UrlMetadataCrudController::class),
                ],
                [
                    // Edit and not new: the rows are created by the c975l:url-metadata:sync command from what the bundles declare, Action::NEW being disabled on purpose (see UrlMetadataCrudController::configureActions())
                    'label' => 'label.guided_step_config_url_metadata_edit',
                    'description' => 'description.guided_step_config_url_metadata_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_config_url_metadata_title',
                    'description' => 'description.guided_step_config_url_metadata_title',
                    'highlight' => '#UrlMetadata_title',
                ],
                [
                    'label' => 'label.guided_step_config_url_metadata_summary',
                    'description' => 'description.guided_step_config_url_metadata_summary',
                    'highlight' => '#UrlMetadata_summarySocialNetwork',
                ],
                [
                    'label' => 'label.guided_step_config_url_metadata_image',
                    'description' => 'description.guided_step_config_url_metadata_image',
                    'highlight' => '#UrlMetadata_ogImage',
                ],
                [
                    'label' => 'label.guided_step_config_url_metadata_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_config_url_metadata_done',
                    'description' => 'description.guided_step_config_url_metadata_done',
                ],
            ],
        ];
    }

    private function indexUrl(string $controllerFqcn): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
