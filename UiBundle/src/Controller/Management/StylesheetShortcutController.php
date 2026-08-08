<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

// Rebuilds bundles/build/site.css + admin.css on demand. ThemeVariablesCssListener already does it whenever a "theme-" config is saved, but an edit to a site's own assets/styles/themes/*.css is a file change no entity event reports: without this tile, that edit only reaches the compiled stylesheet at the next cache:warmup - i.e. never, on a managed host where the admin has no console
class StylesheetShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_ui_stylesheet_compile
    public const COMPILE_ROUTE = 'management_ui_stylesheet_compile';

    public function __construct(
        private readonly StylesheetCacheWarmer $stylesheetCacheWarmer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(
        path: '/ui/stylesheet/compile',
        name: 'ui_stylesheet_compile',
        options: ['methods' => ['POST']]
    )]
    public function compile(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($this->isCsrfTokenValid(self::COMPILE_ROUTE, $request->request->get('_token'))) {
            $this->stylesheetCacheWarmer->compileAll();
            $this->addFlash('success', $this->translator->trans('flash.stylesheet_compiled', [], 'ui'));
        }

        return $this->redirectToRoute('management');
    }
}
