<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\AiAssistantController;
use c975L\UiBundle\Controller\Management\AiSearchAnswerCrudController;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FontCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Controller\Management\ReviewCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Service\AiSiteSearchClient;
use c975L\UiBundle\Service\ReviewService;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly ReviewService $reviewService,
        private readonly AiSiteSearchClient $aiSiteSearchClient,
    ) {
    }

    // Matches ConfigBundle's/SiteBundle's section value so this bundle's CRUD entries merge into the same group
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.management',
            'translation_domain' => 'site',
            'icon' => 'fas fa-sliders',
        ];
    }

    // This bundle's own CRUD entries, declared here rather than by SiteBundle as they used to be: a site running Config+Ui plus a satellite bundle (shop, book...) but no SiteBundle had no media library, no forms and no email templates in its back-office at all
    public function getMenus(): array
    {
        $menus = [
            'media' => [
                'controller' => MediaCrudController::class,
                'label' => 'label.media_library',
                'narration' => 'narration.media_library',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-photo-film',
                // The bar MediaCrudController sets on its own index, and the reason this entry is one of the two this bundle keeps essential - the reviews below being the other, both being looked at on any given day
                'role' => $this->configService->get('site-role-editor'),
                // Same key as the screen's own explanatory text - one text, reused, not a separate onboarding-only string (see MenuProviderInterface::getMenus())
                'description' => 'label.info_media',
            ],
            'form' => [
                'controller' => FormCrudController::class,
                'label' => 'label.forms',
                'narration' => 'narration.forms',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-wpforms',
                'tier' => 'advanced',
                'description' => 'label.info_form',
            ],
            'email_template' => [
                'controller' => EmailTemplateCrudController::class,
                'label' => 'label.email_templates',
                'narration' => 'narration.email_templates',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-envelope-open-text',
                'tier' => 'advanced',
                'description' => 'label.info_email_template',
            ],
            'font' => [
                'controller' => FontCrudController::class,
                'label' => 'label.fonts',
                'narration' => 'narration.fonts',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-font',
                'tier' => 'advanced',
                // The bar FontCrudController sets on its own index, only its "export selection" being stricter
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_font',
            ],
            'site_graphic' => [
                'controller' => SiteGraphicCrudController::class,
                // Its missing graphics are already an alert (see SiteGraphicAlertProvider): the unused features panel would say it twice
                'creatable' => false,
                'label' => 'label.site_graphics',
                'narration' => 'narration.site_graphics',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-image',
                'tier' => 'advanced',
                // The bar SiteGraphicCrudController sets on its own index, the graphics being content like any other
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_site_graphic',
            ],
        ];

        // Only displayed if reviews are turned on site-wide: a screen for a feature the site neither collects nor shows is one more thing to explain in a sidebar
        if ($this->reviewService->isEnabled()) {
            $menus['review'] = [
                'controller' => ReviewCrudController::class,
                // Lists what happened rather than what an admin makes: empty, it is no feature left unused (see UnusedFeatureBuilder)
                'creatable' => false,
                'label' => 'label.reviews',
                'narration' => 'narration.reviews',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-star',
                // The bar ReviewCrudController states on its own rows
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_reviews',
            ];
        }

        // Same reading as the reviews: only once the site search is configured, the screen listing what it was asked
        if ($this->aiSiteSearchClient->isEnabled()) {
            $menus['ai_search_answer'] = [
                'controller' => AiSearchAnswerCrudController::class,
                // Lists what happened rather than what an admin makes: empty, it is no feature left unused (see UnusedFeatureBuilder)
                'creatable' => false,
                'label' => 'label.ai_search_answers',
                'narration' => 'narration.ai_search_answers',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-magnifying-glass',
                'tier' => 'advanced',
                // The bar AiSearchAnswerCrudController states on its own rows
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_ai_search_answers',
            ];
        }

        return $menus;
    }

    // The bundle's screens that are no entity CRUD, the ecosystem's block showcase now sitting in the dashboard's header (see ConfigBundle's EcosystemUrls)
    public function getLinks(): array
    {
        return [
            'legal_models' => [
                'name' => LegalModelController::INDEX_ROUTE,
                'label' => 'label.legal_models',
                'narration' => 'narration.legal_models',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-scale-balanced',
                // Same gate as the screen itself (see LegalModelController) - without it the link shows to a back-office user who would only ever get a 403 out of it
                'role' => $this->configService->get('site-role-editor'),
                // Set up once when the site opens, revisited a couple of times a year at most
                'tier' => 'advanced',
                'description' => 'label.info_legal_model',
            ],
            'ai_assistant' => [
                // Composed here: "Donovan" is hardcoded, only the parenthesized half is translated
                'label' => \sprintf(
                    'Donovan (%s)',
                    $this->translator->trans('label.ai_assistant_menu_suffix', [], 'ui'),
                ),
                'narration' => 'narration.ai_assistant',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-robot',
                'name' => AiAssistantController::INDEX_ROUTE,
                // Matches the page's own minimum bar; a plain editor could act on neither section
                'role' => $this->configService->get('site-role-admin'),
                // Same key as _ai_assistant_base.html.twig's own subtitle, right under its <h1>
                'description' => 'label.ai_assistant_subtitle',
            ],
        ];
    }
}
