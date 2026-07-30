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
use c975L\UiBundle\Controller\Management\BlockShortcutController;
use Symfony\Contracts\Translation\TranslatorInterface;

class UiShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getShortcuts(): array
    {
        return [
            [
                'label' => $this->translator->trans('label.block_clear_cache', [], 'ui'),
                'icon' => 'fas fa-broom',
                'route' => BlockShortcutController::CLEAR_CACHE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_MAINTENANCE,
            ],
        ];
    }
}
