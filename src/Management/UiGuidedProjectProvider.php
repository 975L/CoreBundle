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
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
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
            $this->mediaProject(),
            $this->formProject(),
            $this->emailTemplateProject(),
        ];
    }

    // One image, uploaded once, used everywhere - the alternative being the same photo re-uploaded per block
    private function mediaProject(): array
    {
        return [
            'slug' => 'ui-media',
            'label' => 'label.guided_project_ui_media',
            'description' => 'description.guided_project_ui_media',
            'translation_domain' => 'ui',
            'order' => 90,
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_media_open',
                    'description' => 'description.guided_step_ui_media_open',
                    'url' => $this->indexUrl(MediaCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_media_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_ui_media_file',
                    'description' => 'description.guided_step_ui_media_file',
                    'highlight' => 'input[type="file"]',
                ],
                [
                    'label' => 'label.guided_step_ui_media_alt',
                    'description' => 'description.guided_step_ui_media_alt',
                    'highlight' => '#Media_alt',
                ],
                [
                    'label' => 'label.guided_step_ui_media_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_media_reuse',
                    'description' => 'description.guided_step_ui_media_reuse',
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

    private function indexUrl(string $controllerFqcn): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
