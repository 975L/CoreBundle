<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\BlockShortcutController;
use c975L\UiBundle\Controller\Management\EmailDebugShortcutController;
use c975L\UiBundle\Controller\Management\ReviewShortcutController;
use c975L\UiBundle\Controller\Management\StylesheetShortcutController;
use Symfony\Contracts\Translation\TranslatorInterface;

class UiShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getShortcuts(): array
    {
        $emailDebugEnabled = $this->configService->getBool($this->configService->get('email-debug'));
        $reviewsEnabled = $this->configService->getBool($this->configService->get('ui-enable-reviews'));

        return [
            [
                'label' => $this->translator->trans('label.block_clear_cache', [], 'ui'),
                'icon' => 'fas fa-broom',
                'route' => BlockShortcutController::CLEAR_CACHE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_MAINTENANCE,
            ],
            [
                'label' => $this->translator->trans('label.stylesheet_compile', [], 'ui'),
                'icon' => 'fas fa-paint-roller',
                'route' => StylesheetShortcutController::COMPILE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_MAINTENANCE,
            ],
            [
                'label' => $this->translator->trans(
                    $emailDebugEnabled ? 'label.email_debug_disable' : 'label.email_debug_enable',
                    [],
                    'ui',
                ),
                'icon' => 'fas fa-bug',
                'route' => EmailDebugShortcutController::TOGGLE_ROUTE,
                'active' => $emailDebugEnabled,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_TOGGLE,
            ],
            [
                'label' => $this->translator->trans(
                    $reviewsEnabled ? 'label.reviews_disable' : 'label.reviews_enable',
                    [],
                    'ui',
                ),
                'icon' => 'fas fa-star',
                'route' => ReviewShortcutController::TOGGLE_ROUTE,
                'active' => $reviewsEnabled,
                'role' => $this->configService->get('site-role-admin'),
                'category' => ShortcutProviderInterface::CATEGORY_TOGGLE,
            ],
        ];
    }
}
