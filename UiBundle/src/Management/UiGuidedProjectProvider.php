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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\AiAssistantController;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FontCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\FormFieldTemplateCrudController;
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Controller\Management\ReviewCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Service\ReviewService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's guided projects, running the 3000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They open on the media library first, the one screen of this bundle the sidebar keeps essential, then the three a site puts in place as it opens (its graphics, its legal documents, the key its rephrasing runs on), the five occasional ones last. Each carries the role its own screen is gated by, so a parcours is never offered to someone its very first step turns away. Only the opening step of each carries an url: from there the parcours walks the screen the user has been sent to, highlighting the button or the field they are meant to use next - one they click themselves, which brings the panel back on that very step (see ConfigBundle's assets/js/guided-project.js)
// No parcours teaches a block kind, however much one asks for beforehand (the "map" one wants its provider picked in Configuration first): a block is composed inside a page, and a page belongs to SiteBundle, which is where that parcours goes. What is walked here is this bundle's own screens.
class UiGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        // The legal documents screen is a plain controller carrying an #[AdminRoute], not a CRUD one, so its url comes from the router rather than from EasyAdmin's generator
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReviewService $reviewService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        $projects = [
            $this->mediaProject(),
            $this->siteGraphicProject(),
            $this->legalModelProject(),
            $this->aiAssistantProject(),
            $this->formProject(),
            $this->calculatorProject(),
            $this->formFieldTemplateProject(),
            $this->emailTemplateProject(),
            $this->fontProject(),
        ];

        // Same gate as the screen it walks to (see MenuProvider): a walk-through of a feature the site neither collects nor shows is one more thing to explain
        if ($this->reviewService->isEnabled()) {
            $projects[] = $this->reviewProject();
        }

        return $projects;
    }

    // The one screen where a review becomes readable, or does not - a site collecting reviews nobody publishes shows none, and nothing says why
    private function reviewProject(): array
    {
        return [
            'slug' => 'ui-review',
            'label' => 'label.guided_project_ui_review',
            'description' => 'description.guided_project_ui_review',
            'translation_domain' => 'ui',
            // Last of the ten, the walk-through being appended after the nine above
            'order' => 3100,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_review_open',
                    'description' => 'description.guided_step_ui_review_open',
                    'narration' => 'narration.guided_step_ui_review_open',
                    'url' => $this->indexUrl(ReviewCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_review_pick',
                    'description' => 'description.guided_step_ui_review_pick',
                    'narration' => 'narration.guided_step_ui_review_pick',
                    'highlight' => '.action-edit',
                ],
                [
                    // The two custom actions ReviewCrudController builds the screen around, added to the edit page as well as to the list - EasyAdmin names their buttons after the actions themselves, same as ".action-bulkImport"
                    'label' => 'label.guided_step_ui_review_decide',
                    'description' => 'description.guided_step_ui_review_decide',
                    'narration' => 'narration.guided_step_ui_review_decide',
                    'highlight' => '.action-publishReview',
                ],
                [
                    // The status field is still there and still editable on a local review, for a decision the two buttons above don't cover - kept after them rather than before, the buttons being what the screen exists for
                    'label' => 'label.guided_step_ui_review_status',
                    'description' => 'description.guided_step_ui_review_status',
                    'narration' => 'narration.guided_step_ui_review_status',
                    'highlight' => '#Review_status',
                ],
                [
                    'label' => 'label.guided_step_ui_review_reply',
                    'description' => 'description.guided_step_ui_review_reply',
                    'narration' => 'narration.guided_step_ui_review_reply',
                    'highlight' => '#Review_replyComment',
                ],
                [
                    'label' => 'label.guided_step_ui_review_save',
                    'narration' => 'narration.guided_step_ui_review_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_review_done',
                    'description' => 'description.guided_step_ui_review_done',
                    'narration' => 'narration.guided_step_ui_review_done',
                ],
            ],
        ];
    }

    // Every image the site holds ends up here whatever screen uploaded it, which is what makes this the one place to fix what a picture says about itself
    private function mediaProject(): array
    {
        return [
            'slug' => 'ui-media',
            'label' => 'label.guided_project_ui_media',
            'description' => 'description.guided_project_ui_media',
            'translation_domain' => 'ui',
            'order' => 3010,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_media_open',
                    'description' => 'description.guided_step_ui_media_open',
                    'narration' => 'narration.guided_step_ui_media_open',
                    'url' => $this->indexUrl(MediaCrudController::class),
                ],
                [
                    // The index is a thumbnail gallery, not an EasyAdmin table (see media_index.html.twig), so there is no ".action-edit" to point at - the thumbnail is itself the link to the form. A site-wide graphic opens SiteGraphicCrudController's own form instead, which holds neither of the two fields the next steps point at, so its thumbnail is left out
                    'label' => 'label.guided_step_ui_media_pick',
                    'description' => 'description.guided_step_ui_media_pick',
                    'narration' => 'narration.guided_step_ui_media_pick',
                    'highlight' => '.management-media-grid__item:not(.management-media-grid__item--site-graphic)',
                ],
                [
                    'label' => 'label.guided_step_ui_media_alt',
                    'description' => 'description.guided_step_ui_media_alt',
                    'narration' => 'narration.guided_step_ui_media_alt',
                    'highlight' => '#Media_alt',
                ],
                [
                    'label' => 'label.guided_step_ui_media_credits',
                    'description' => 'description.guided_step_ui_media_credits',
                    'narration' => 'narration.guided_step_ui_media_credits',
                    'highlight' => '#Media_credits',
                ],
                [
                    'label' => 'label.guided_step_ui_media_save',
                    'narration' => 'narration.guided_step_ui_media_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_media_done',
                    'description' => 'description.guided_step_ui_media_done',
                    'narration' => 'narration.guided_step_ui_media_done',
                ],
            ],
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
            'order' => 3020,
            // Its own screen is the editor's (see SiteGraphicCrudController), the graphics being content like any other
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_site_graphic_open',
                    'description' => 'description.guided_step_ui_site_graphic_open',
                    'narration' => 'narration.guided_step_ui_site_graphic_open',
                    'url' => $this->indexUrl(SiteGraphicCrudController::class),
                ],
                [
                    // The index renders one button per graphic still missing, each opening the upload form with its role pre-picked through SiteGraphicCrudController::ROLE_PARAMETER - a plainer ".action-new" would point at the generic form the screen precisely spares the user (see site_graphic_crud_index.html.twig)
                    'label' => 'label.guided_step_ui_site_graphic_pick',
                    'description' => 'description.guided_step_ui_site_graphic_pick',
                    'narration' => 'narration.guided_step_ui_site_graphic_pick',
                    'highlight' => 'a[href*="' . SiteGraphicCrudController::ROLE_PARAMETER . '"]',
                ],
                [
                    'label' => 'label.guided_step_ui_site_graphic_file',
                    'description' => 'description.guided_step_ui_site_graphic_file',
                    'narration' => 'narration.guided_step_ui_site_graphic_file',
                    'highlight' => 'input[type="file"]',
                ],
                [
                    'label' => 'label.guided_step_ui_site_graphic_save',
                    'narration' => 'narration.guided_step_ui_site_graphic_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_site_graphic_done',
                    'description' => 'description.guided_step_ui_site_graphic_done',
                    'narration' => 'narration.guided_step_ui_site_graphic_done',
                ],
            ],
        ];
    }

    // The legal documents a site owes its visitors are shipped as models it only ever adjusts, and nothing says so - an editor rewriting one from scratch loses the updates the bundle keeps making to it
    private function legalModelProject(): array
    {
        return [
            'slug' => 'ui-legal-model',
            'label' => 'label.guided_project_ui_legal_model',
            'description' => 'description.guided_project_ui_legal_model',
            'translation_domain' => 'ui',
            'order' => 3030,
            // The gate both actions of LegalModelController set on themselves
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_legal_model_open',
                    'description' => 'description.guided_step_ui_legal_model_open',
                    'narration' => 'narration.guided_step_ui_legal_model_open',
                    'url' => $this->urlGenerator->generate(LegalModelController::INDEX_ROUTE),
                ],
                [
                    // The row's own "customize" link, which the index renders only for a model this bundle still ships - the screen 404s on any other (see legal_model_index.html.twig)
                    'label' => 'label.guided_step_ui_legal_model_customize',
                    'description' => 'description.guided_step_ui_legal_model_customize',
                    'narration' => 'narration.guided_step_ui_legal_model_customize',
                    'highlight' => '[data-legal-model-customize]',
                ],
                [
                    // Each section of the document is a card of the same collection markup the block forms use (see legal_model_customize.html.twig)
                    'label' => 'label.guided_step_ui_legal_model_unit',
                    'description' => 'description.guided_step_ui_legal_model_unit',
                    'narration' => 'narration.guided_step_ui_legal_model_unit',
                    'highlight' => '.field-collection-item',
                ],
                [
                    'label' => 'label.guided_step_ui_legal_model_reset',
                    'description' => 'description.guided_step_ui_legal_model_reset',
                    'narration' => 'narration.guided_step_ui_legal_model_reset',
                    'highlight' => '[data-action="legal-model#reset"]',
                ],
                [
                    'label' => 'label.guided_step_ui_legal_model_extra',
                    'description' => 'description.guided_step_ui_legal_model_extra',
                    'narration' => 'narration.guided_step_ui_legal_model_extra',
                    'highlight' => '[data-action="legal-model#add"]',
                ],
                [
                    // A plain Symfony form on its own screen, not an EasyAdmin CRUD page: there is no "saveAndReturn" action to name here, same as the font bulk import step
                    'label' => 'label.guided_step_ui_legal_model_apply',
                    'description' => 'description.guided_step_ui_legal_model_apply',
                    'narration' => 'narration.guided_step_ui_legal_model_apply',
                    'highlight' => 'form button[type="submit"]',
                ],
                [
                    'label' => 'label.guided_step_ui_legal_model_done',
                    'description' => 'description.guided_step_ui_legal_model_done',
                    'narration' => 'narration.guided_step_ui_legal_model_done',
                ],
            ],
        ];
    }

    // Rephrasing is offered under every text field of the back office, and nobody presses a button they have never seen work once - so the parcours walks the free-standing textarea this screen holds for exactly that
    private function aiAssistantProject(): array
    {
        return [
            'slug' => 'ui-ai-assistant',
            'label' => 'label.guided_project_ui_ai_assistant',
            'description' => 'description.guided_project_ui_ai_assistant',
            'translation_domain' => 'ui',
            'order' => 3040,
            // The bar index() sets on itself, and the one rephrase() answers to - the question/answer half of the screen asks for ROLE_SUPER_ADMIN and simply does not render below it, which is why no step points into it
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_ai_assistant_open',
                    'description' => 'description.guided_step_ui_ai_assistant_open',
                    'narration' => 'narration.guided_step_ui_ai_assistant_open',
                    'url' => $this->urlGenerator->generate(AiAssistantController::INDEX_ROUTE),
                ],
                [
                    // The list of what is still missing, one link per setting, which the screen stops rendering once nothing is (see _ai_assistant_base.html.twig) - the two halves of this parcours are exclusive by design, a site yet to be set up seeing this step and the ones after it only later
                    'label' => 'label.guided_step_ui_ai_assistant_setup',
                    'description' => 'description.guided_step_ui_ai_assistant_setup',
                    'narration' => 'narration.guided_step_ui_ai_assistant_setup',
                    'highlight' => '[data-ai-rephrase-setup]',
                ],
                [
                    // The screen's own textarea, tied to no content of the site: a text can be tried out here before the button is ever pressed on a real page
                    'label' => 'label.guided_step_ui_ai_assistant_text',
                    'description' => 'description.guided_step_ui_ai_assistant_text',
                    'narration' => 'narration.guided_step_ui_ai_assistant_text',
                    'highlight' => '#ai-rephrase-freeform',
                ],
                [
                    'label' => 'label.guided_step_ui_ai_assistant_style',
                    'description' => 'description.guided_step_ui_ai_assistant_style',
                    'narration' => 'narration.guided_step_ui_ai_assistant_style',
                    'highlight' => '.ai-rephrase__style',
                ],
                [
                    'label' => 'label.guided_step_ui_ai_assistant_length',
                    'description' => 'description.guided_step_ui_ai_assistant_length',
                    'narration' => 'narration.guided_step_ui_ai_assistant_length',
                    'highlight' => '.ai-rephrase__length',
                ],
                [
                    'label' => 'label.guided_step_ui_ai_assistant_run',
                    'description' => 'description.guided_step_ui_ai_assistant_run',
                    'narration' => 'narration.guided_step_ui_ai_assistant_run',
                    'highlight' => '.ai-rephrase__button',
                ],
                [
                    'label' => 'label.guided_step_ui_ai_assistant_done',
                    'description' => 'description.guided_step_ui_ai_assistant_done',
                    'narration' => 'narration.guided_step_ui_ai_assistant_done',
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
            'order' => 3050,
            // FormCrudController is admin-only, an action key being what a form does once submitted - an editor offered this parcours would get a 403 on its very first step
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_form_open',
                    'description' => 'description.guided_step_ui_form_open',
                    'narration' => 'narration.guided_step_ui_form_open',
                    'url' => $this->indexUrl(FormCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_form_new',
                    'narration' => 'narration.guided_step_ui_form_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_ui_form_name',
                    'description' => 'description.guided_step_ui_form_name',
                    'narration' => 'narration.guided_step_ui_form_name',
                    'highlight' => '#Form_name',
                ],
                [
                    'label' => 'label.guided_step_ui_form_action',
                    'description' => 'description.guided_step_ui_form_action',
                    'narration' => 'narration.guided_step_ui_form_action',
                    'highlight' => '#Form_action',
                ],
                [
                    'label' => 'label.guided_step_ui_form_fields',
                    'description' => 'description.guided_step_ui_form_fields',
                    'narration' => 'narration.guided_step_ui_form_fields',
                    'highlight' => '[data-form-field-template-catalog-url]',
                ],
                [
                    'label' => 'label.guided_step_ui_form_save',
                    'narration' => 'narration.guided_step_ui_form_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    // No highlight and none possible: the selector sits on the edit screen (drawn only on a site declaring several languages, see form_crud_edit.html.twig) while this step is read from the index the save above returned to
                    'label' => 'label.guided_step_ui_form_translate',
                    'description' => 'description.guided_step_ui_form_translate',
                    'narration' => 'narration.guided_step_ui_form_translate',
                ],
                [
                    'label' => 'label.guided_step_ui_form_place',
                    'description' => 'description.guided_step_ui_form_place',
                    'narration' => 'narration.guided_step_ui_form_place',
                ],
            ],
        ];
    }

    // The same screen as the project above, walked for what turns a form into a calculator - kept apart rather than tacked onto it: a contact form has no formula to write, and the two orders that matter here (save the fields before naming their variables, place an output under the ones it reads) are the whole of what an admin gets wrong on their own
    private function calculatorProject(): array
    {
        return [
            'slug' => 'ui-calculator',
            'label' => 'label.guided_project_ui_calculator',
            'description' => 'description.guided_project_ui_calculator',
            'translation_domain' => 'ui',
            // Right after the form it extends, and before the field catalog both of them pick from
            'order' => 3060,
            // The screen's own gate, the same FormCrudController sets
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_calculator_open',
                    'description' => 'description.guided_step_ui_calculator_open',
                    'narration' => 'narration.guided_step_ui_calculator_open',
                    'url' => $this->indexUrl(FormCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_new',
                    'narration' => 'narration.guided_step_ui_calculator_new',
                    'highlight' => '.action-new',
                ],
                [
                    // The parcours went straight from the "create" button to the fields, and the form it teaches to build cannot be saved without a name - a red field the reader met with no step explaining it
                    'label' => 'label.guided_step_ui_calculator_name',
                    'description' => 'description.guided_step_ui_calculator_name',
                    'narration' => 'narration.guided_step_ui_calculator_name',
                    'highlight' => '#Form_name',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_fields',
                    'description' => 'description.guided_step_ui_calculator_fields',
                    'narration' => 'narration.guided_step_ui_calculator_fields',
                    'highlight' => '[data-form-field-template-catalog-url]',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_save_fields',
                    'description' => 'description.guided_step_ui_calculator_save_fields',
                    'narration' => 'narration.guided_step_ui_calculator_save_fields',
                    // Not ".action-saveAndContinue": EasyAdmin only offers that one on an edit page, and this parcours walks an admin creating their first calculator
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    // Saving returned to the index, and every step below points at the edit page again - so the parcours has to send the admin back into it rather than highlight nothing
                    'label' => 'label.guided_step_ui_calculator_reopen',
                    'description' => 'description.guided_step_ui_calculator_reopen',
                    'narration' => 'narration.guided_step_ui_calculator_reopen',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_outputs',
                    'description' => 'description.guided_step_ui_calculator_outputs',
                    'narration' => 'narration.guided_step_ui_calculator_outputs',
                    'highlight' => '[data-form-outputs-collection]',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_hidden',
                    'description' => 'description.guided_step_ui_calculator_hidden',
                    'narration' => 'narration.guided_step_ui_calculator_hidden',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_highlighted',
                    'description' => 'description.guided_step_ui_calculator_highlighted',
                    'narration' => 'narration.guided_step_ui_calculator_highlighted',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_outputs_first',
                    'description' => 'description.guided_step_ui_calculator_outputs_first',
                    'narration' => 'narration.guided_step_ui_calculator_outputs_first',
                    'highlight' => '#Form_outputsFirst',
                ],
                [
                    'label' => 'label.guided_step_ui_calculator_place',
                    'description' => 'description.guided_step_ui_calculator_place',
                    'narration' => 'narration.guided_step_ui_calculator_place',
                ],
            ],
        ];
    }

    // A field set up once is dropped into any form in one click, always worded the same way - the catalog the "fields" collection of the previous project picks from, which no sidebar entry ever names
    private function formFieldTemplateProject(): array
    {
        return [
            'slug' => 'ui-form-field-template',
            'label' => 'label.guided_project_ui_form_field_template',
            'description' => 'description.guided_project_ui_form_field_template',
            'translation_domain' => 'ui',
            // Slotted between the forms and the e-mail templates, the two screens whose own toolbar opens this catalog, rather than appended after the sequence it belongs in the middle of
            'order' => 3070,
            // The gate FormFieldTemplateCrudController sets on every one of its actions, its own catalog() included
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    // No sidebar entry opens this screen: it is reached by the "formFieldTemplates" toolbar button of FormCrudController and EmailTemplateCrudController alike, which is exactly why it deserves a parcours of its own
                    'label' => 'label.guided_step_ui_form_field_template_open',
                    'description' => 'description.guided_step_ui_form_field_template_open',
                    'narration' => 'narration.guided_step_ui_form_field_template_open',
                    'url' => $this->indexUrl(FormFieldTemplateCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_new',
                    'narration' => 'narration.guided_step_ui_form_field_template_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_name',
                    'description' => 'description.guided_step_ui_form_field_template_name',
                    'narration' => 'narration.guided_step_ui_form_field_template_name',
                    'highlight' => '#FormFieldTemplate_name',
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_label',
                    'description' => 'description.guided_step_ui_form_field_template_label',
                    'narration' => 'narration.guided_step_ui_form_field_template_label',
                    'highlight' => '#FormFieldTemplate_fieldLabel',
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_type',
                    'description' => 'description.guided_step_ui_form_field_template_type',
                    'narration' => 'narration.guided_step_ui_form_field_template_type',
                    'highlight' => '#FormFieldTemplate_type',
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_required',
                    'description' => 'description.guided_step_ui_form_field_template_required',
                    'narration' => 'narration.guided_step_ui_form_field_template_required',
                    'highlight' => '#FormFieldTemplate_required',
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_save',
                    'narration' => 'narration.guided_step_ui_form_field_template_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_form_field_template_done',
                    'description' => 'description.guided_step_ui_form_field_template_done',
                    'narration' => 'narration.guided_step_ui_form_field_template_done',
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
            'order' => 3080,
            // Same gate as FormCrudController, and for the same reason: what an e-mail template holds is sent to real people
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_email_template_open',
                    'description' => 'description.guided_step_ui_email_template_open',
                    'narration' => 'narration.guided_step_ui_email_template_open',
                    'url' => $this->indexUrl(EmailTemplateCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_edit',
                    'narration' => 'narration.guided_step_ui_email_template_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_blocks',
                    'description' => 'description.guided_step_ui_email_template_blocks',
                    'narration' => 'narration.guided_step_ui_email_template_blocks',
                    'highlight' => '[data-ea-collection-field]',
                ],
                [
                    // An expanded multiple ChoiceField, so Symfony puts the field's own id on the box wrapping the checkboxes rather than on an input. attachmentField() drops it entirely when no bundle offers anything to attach - this one always ships LegalDocumentAttachmentProvider, so the box is there wherever this parcours is
                    'label' => 'label.guided_step_ui_email_template_attachments',
                    'description' => 'description.guided_step_ui_email_template_attachments',
                    'narration' => 'narration.guided_step_ui_email_template_attachments',
                    'highlight' => '#EmailTemplate_attachments',
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_save',
                    'narration' => 'narration.guided_step_ui_email_template_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_ui_email_template_check',
                    'description' => 'description.guided_step_ui_email_template_check',
                    'narration' => 'narration.guided_step_ui_email_template_check',
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
            'order' => 3090,
            // The screen this walks is the editor's; only its "exportSelection" is stricter, and no step here uses it (see FontCrudController)
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_ui_font_open',
                    'description' => 'description.guided_step_ui_font_open',
                    'narration' => 'narration.guided_step_ui_font_open',
                    'url' => $this->indexUrl(FontCrudController::class),
                ],
                [
                    // A custom action, so EasyAdmin names its button after it just the same (see ActionFactory) - the toolbar button FontCrudController adds for FontBulkImportController
                    'label' => 'label.guided_step_ui_font_bulk',
                    'description' => 'description.guided_step_ui_font_bulk',
                    'narration' => 'narration.guided_step_ui_font_bulk',
                    'highlight' => '.action-bulkImport',
                ],
                [
                    'label' => 'label.guided_step_ui_font_files',
                    'description' => 'description.guided_step_ui_font_files',
                    'narration' => 'narration.guided_step_ui_font_files',
                    'highlight' => 'input[type="file"][multiple]',
                ],
                [
                    // The bulk import screen is a plain form of its own, not an EasyAdmin CRUD page: no ".action-" button to name here (see font_bulk_import.html.twig)
                    'label' => 'label.guided_step_ui_font_import',
                    'description' => 'description.guided_step_ui_font_import',
                    'narration' => 'narration.guided_step_ui_font_import',
                    'highlight' => 'form[enctype="multipart/form-data"] button[type="submit"]',
                ],
                [
                    'label' => 'label.guided_step_ui_font_fix',
                    'description' => 'description.guided_step_ui_font_fix',
                    'narration' => 'narration.guided_step_ui_font_fix',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_ui_font_done',
                    'description' => 'description.guided_step_ui_font_done',
                    'narration' => 'narration.guided_step_ui_font_done',
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
