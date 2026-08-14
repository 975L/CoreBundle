<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FontCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided projects, continuing the order sequence after ConfigBundle (10-30) and SiteBundle (50-80). Only the opening step of each carries an url: from there the parcours walks the screen the user has been sent to, highlighting the button or the field they are meant to use next - one they click themselves, which brings the panel back on that very step (see ConfigBundle's assets/js/guided-project.js)
class UiGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            $this->siteGraphicProject(),
            $this->formProject(),
            $this->emailTemplateProject(),
            $this->fontProject(),
        ];
    }

    // The handful of images a site is recognised by, which nothing else puts in place - a favicon missing is noticed by every visitor and by no error log
    private function siteGraphicProject(): array
    {
        return [
            'slug' => 'ui-site-graphic',
            'label' => 'label.guided_project_ui_site_graphic',
            'description' => 'description.guided_project_ui_site_graphic',
            'translation_domain' => 'ui',
            'order' => 90,
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_site_graphic_open',
                    'description' => 'description.guided_step_ui_site_graphic_open',
                    'url' => $this->indexUrl(SiteGraphicCrudController::class),
                ],
                [
                    // The index renders one button per graphic still missing, each opening the upload form with its role pre-picked through SiteGraphicCrudController::ROLE_PARAMETER - a plainer ".action-new" would point at the generic form the screen precisely spares the user (see site_graphic_crud_index.html.twig)
                    'label' => 'label.guided_step_ui_site_graphic_pick',
                    'description' => 'description.guided_step_ui_site_graphic_pick',
                    'highlight' => 'a[href*="' . SiteGraphicCrudController::ROLE_PARAMETER . '"]',
                ],
                [
                    'label' => 'label.guided_step_ui_site_graphic_file',
                    'description' => 'description.guided_step_ui_site_graphic_file',
                    'highlight' => 'input[type="file"]',
                ],
                [
                    'label' => 'label.guided_step_ui_site_graphic_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_site_graphic_done',
                    'description' => 'description.guided_step_ui_site_graphic_done',
                ],
            ],
        ];
    }

    // A form is built here field by field, then dropped into a page through a block of its own
    private function formProject(): array
    {
        return [
            'slug' => 'ui-form',
            'label' => 'label.guided_project_ui_form',
            'description' => 'description.guided_project_ui_form',
            'translation_domain' => 'ui',
            'order' => 100,
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_form_open',
                    'description' => 'description.guided_step_ui_form_open',
                    'url' => $this->indexUrl(FormCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_form_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_ui_form_name',
                    'description' => 'description.guided_step_ui_form_name',
                    'highlight' => '#Form_name',
                ],
                [
                    'label' => 'label.guided_step_ui_form_action',
                    'description' => 'description.guided_step_ui_form_action',
                    'highlight' => '#Form_action',
                ],
                [
                    'label' => 'label.guided_step_ui_form_fields',
                    'description' => 'description.guided_step_ui_form_fields',
                    'highlight' => '[data-form-field-template-catalog-url]',
                ],
                [
                    'label' => 'label.guided_step_ui_form_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_form_place',
                    'description' => 'description.guided_step_ui_form_place',
                ],
            ],
        ];
    }

    // The mails a site sends are the part of it nobody ever looks at, until a visitor receives one
    private function emailTemplateProject(): array
    {
        return [
            'slug' => 'ui-email-template',
            'label' => 'label.guided_project_ui_email_template',
            'description' => 'description.guided_project_ui_email_template',
            'translation_domain' => 'ui',
            'order' => 110,
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_email_template_open',
                    'description' => 'description.guided_step_ui_email_template_open',
                    'url' => $this->indexUrl(EmailTemplateCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_blocks',
                    'description' => 'description.guided_step_ui_email_template_blocks',
                    'highlight' => '[data-ea-collection-field]',
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_check',
                    'description' => 'description.guided_step_ui_email_template_check',
                ],
            ],
        ];
    }

    // A whole font family lands in one upload rather than one row typed per weight - the part of the screen nobody finds on their own
    private function fontProject(): array
    {
        return [
            'slug' => 'ui-font',
            'label' => 'label.guided_project_ui_font',
            'description' => 'description.guided_project_ui_font',
            'translation_domain' => 'ui',
            'order' => 120,
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_font_open',
                    'description' => 'description.guided_step_ui_font_open',
                    'url' => $this->indexUrl(FontCrudController::class),
                ],
                [
                    // A custom action, so EasyAdmin names its button after it just the same (see ActionFactory) - the toolbar button FontCrudController adds for FontBulkImportController
                    'label' => 'label.guided_step_ui_font_bulk',
                    'description' => 'description.guided_step_ui_font_bulk',
                    'highlight' => '.action-bulkImport',
                ],
                [
                    'label' => 'label.guided_step_ui_font_files',
                    'description' => 'description.guided_step_ui_font_files',
                    'highlight' => 'input[type="file"][multiple]',
                ],
                [
                    // The bulk import screen is a plain form of its own, not an EasyAdmin CRUD page: no ".action-" button to name here (see font_bulk_import.html.twig)
                    'label' => 'label.guided_step_ui_font_import',
                    'description' => 'description.guided_step_ui_font_import',
                    'highlight' => 'form[enctype="multipart/form-data"] button[type="submit"]',
                ],
                [
                    'label' => 'label.guided_step_ui_font_fix',
                    'description' => 'description.guided_step_ui_font_fix',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_ui_font_done',
                    'description' => 'description.guided_step_ui_font_done',
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
