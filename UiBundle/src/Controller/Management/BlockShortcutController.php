<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\UiBundle\Service\BlockCacheInvalidator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_ui_block_clear_cache
    public const CLEAR_CACHE_ROUTE = 'management_ui_block_clear_cache';

    public function __construct(
        private readonly BlockCacheInvalidator $blockCacheInvalidator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(
        path: '/ui/block/clear-cache',
        name: 'ui_block_clear_cache',
        options: ['methods' => ['POST']]
    )]
    public function clearCache(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($this->isCsrfTokenValid(self::CLEAR_CACHE_ROUTE, $request->request->get('_token'))) {
            $this->blockCacheInvalidator->invalidateAll();
            $this->addFlash('success', $this->translator->trans('flash.block_cache_cleared', [], 'ui'));
        }

        return $this->redirectToRoute('management');
    }
}
