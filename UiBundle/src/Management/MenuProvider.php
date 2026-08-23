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
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FontCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Controller\Management\ReviewCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Service\ReviewService;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuProvider implements MenuProviderInterface
{
    // Default value of the 'ui-block-showcase-url' config entry, repeated here as the fallback for an app whose configs.json hasn't been reloaded yet
    public const BLOCK_SHOWCASE_URL = 'https://bundles.975l.com/pages/blocks';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly ReviewService $reviewService,
    ) {
    }

    // Matches ConfigBundle's/SiteBundle's section value so this bundle's CRUD entries merge into the same group
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.management',
            'translation_domain' => 'site',
        ];
    }

    // This bundle's own CRUD entries, declared here rather than by SiteBundle as they used to be: a site running Config+Ui plus a satellite bundle (shop, book...) but no SiteBundle had no media library, no forms and no email templates in its back-office at all
    public function getMenus(): array
    {
        $menus = [
            'media' => [
                'controller' => MediaCrudController::class,
                'label' => 'label.media_library',
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
                'translation_domain' => 'ui',
                'icon' => 'fas fa-wpforms',
                'tier' => 'advanced',
                'description' => 'label.info_form',
            ],
            'email_template' => [
                'controller' => EmailTemplateCrudController::class,
                'label' => 'label.email_templates',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-envelope-open-text',
                'tier' => 'advanced',
                'description' => 'label.info_email_template',
            ],
            'font' => [
                'controller' => FontCrudController::class,
                'label' => 'label.fonts',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-font',
                'tier' => 'advanced',
                // The bar FontCrudController sets on its own index, only its "export selection" being stricter
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_font',
            ],
            'site_graphic' => [
                'controller' => SiteGraphicCrudController::class,
                'label' => 'label.site_graphics',
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
                'label' => 'label.reviews',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-star',
                // The bar ReviewCrudController states on its own rows
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_reviews',
            ];
        }

        return $menus;
    }

    // An external url (not a route name), the showcase living on its own site - configurable so an app can point at its own, falling back on the ecosystem's when the key is empty or missing (an app installed before the key existed, its configs.json not reloaded yet)
    public function getLinks(): array
    {
        return [
            'block_showcase' => [
                'label' => 'label.block_showcase',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-shapes',
                'url' => $this->configService->get('ui-block-showcase-url') ?: self::BLOCK_SHOWCASE_URL,
                'target' => '_blank',
                // No local page to reuse text from (external showcase site) - unlike every other description, this one has no crud/index override backing it, so it's its own dedicated key
                'description' => 'label.block_showcase_help',
            ],
            'legal_models' => [
                'name' => LegalModelController::INDEX_ROUTE,
                'label' => 'label.legal_models',
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
