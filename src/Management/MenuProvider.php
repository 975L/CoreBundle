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
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Matches ConfigBundle's/SiteBundle's section value so a future CRUD entry here merges into the same group
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.management',
            'translation_domain' => 'site',
        ];
    }

    public function getMenus(): array
    {
        return [];
    }

    // Fixed external url (not a route name) since every app links to the same external block showcase site
    public function getLinks(): array
    {
        return [
            'block_showcase' => [
                'label' => 'label.block_showcase',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-shapes',
                'url' => 'https://975l.com/pages/blocks',
                'target' => '_blank',
                // No local page to reuse text from (external showcase site) - unlike every other description, this one has no crud/index override backing it, so it's its own dedicated key
                'description' => 'label.block_showcase_help',
            ],
            'ai_assistant' => [
                // Composed here: "Donovan" is hardcoded, only the parenthesized half is translated
                'label' => \sprintf(
                    'Donovan (%s)',
                    $this->translator->trans('label.ai_assistant_menu_suffix', [], 'ui'),
                ),
                'translation_domain' => 'ui',
                'icon' => 'fas fa-robot',
                'name' => 'management_ui_ai_assistant_index',
                // Matches the page's own minimum bar; a plain editor could act on neither section
                'role' => $this->configService->get('site-role-admin'),
                // Same key as _ai_assistant_base.html.twig's own subtitle, right under its <h1>
                'description' => 'label.ai_assistant_subtitle',
            ],
        ];
    }
}
