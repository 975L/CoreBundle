<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Management\AlertBuilder;
use c975L\ConfigBundle\Management\DashboardWidgetBuilder;
use c975L\ConfigBundle\Management\EssentialActionBuilder;
use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\ConfigBundle\Management\GuidedProjectMountBuilder;
use c975L\ConfigBundle\Management\MenuBuilder;
use c975L\ConfigBundle\Management\OnboardingStepBuilder;
use c975L\ConfigBundle\Management\ShortcutBuilder;
use c975L\ConfigBundle\Management\WhatsNewBuilder;
use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Management\PaginatorPageSize;
use c975L\UiBundle\Registry\FormThemeRegistry;
use c975L\UiBundle\Registry\ScriptAdminRegistry;
use c975L\UiBundle\Registry\StylesheetManagementRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/management', routeName: 'management')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly MenuBuilder $menuBuilder,
        private readonly WhatsNewBuilder $whatsNewBuilder,
        private readonly AlertBuilder $alertBuilder,
        private readonly ShortcutBuilder $shortcutBuilder,
        private readonly EssentialActionBuilder $essentialActionBuilder,
        private readonly DashboardWidgetBuilder $dashboardWidgetBuilder,
        private readonly OnboardingStepBuilder $onboardingStepBuilder,
        private readonly GuidedProjectBuilder $guidedProjectBuilder,
        private readonly GuidedProjectMountBuilder $guidedProjectMountBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly ScriptAdminRegistry $scriptAdminRegistry,
        private readonly StylesheetManagementRegistry $stylesheetManagementRegistry,
        private readonly FormThemeRegistry $formThemeRegistry,
        private readonly PaginatorPageSize $paginatorPageSize,
        private readonly TranslatorInterface $translator,
        private readonly Packages $packages,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $debug,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    #[\Override]
    public function index(): Response
    {
        // The floor rather than one named bar: every block below either filters itself by role (menus, links, alerts, shortcuts, guided projects) or is only built for an admin (the essential actions), so what each user gets is the part of the back office that is theirs, not a 403 on the way in. Read BackOfficeAccessVoter for why a plain site-role-editor here would lock out an account holding only the admin one
        $this->denyAccessUnlessGranted(BackOfficeAccessVoter::ACCESS);

        $isAdmin = $this->isGranted($this->configService->get('site-role-admin'));

        return $this->render(
            '@c975LConfig/management/index.html.twig',
            [
                'menus' => $this->menuBuilder->getMenus(),
                'routes' => $this->menuBuilder->getLinks(),
                'alerts' => $this->alertBuilder->getAlerts(),
                'shortcutCategories' => $this->shortcutBuilder->getCategories(),
                'whatsNew' => $this->whatsNewBuilder->getLatest(),
                // The checklist is the site's own setup, start to finish - an editor has the role for none of the screens it links to, so it is not built for them at all rather than gated action by action
                'essentialActions' => $isAdmin ? $this->essentialActionBuilder->getActions() : [],
                'essentialActionsProgress' => $isAdmin ? $this->essentialActionBuilder->getProgress() : [],
                'widgets' => $this->dashboardWidgetBuilder->getWidgets(),
                'onboardingSteps' => $this->onboardingStepBuilder->getSteps(),
                'guidedProjects' => $this->guidedProjectBuilder->getProjects(),
            ]
        );
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            // Fixed path: c975L\SiteBundle's SiteGraphicCrudController always saves the favicon there (see UiMediaNamer)
            ->setTitle('<img src="/favicon.ico">' . $this->configService->get('site-name'))
            ->setFaviconPath('/favicon.ico')
            ->setTranslationDomain('config')
        ;
    }

    // EasyAdmin renders every CRUD form with "{% form_theme form with ea.crud.formThemes only %}" (see vendor/easycorp/.../crud/edit.html.twig) - the "only" keyword means the app-wide twig.form_themes config is never consulted there, so bundle-contributed form themes (Trix editor, icon picker, "used in"...) have to be injected into the Crud config itself instead, here, the single place every CRUD controller's own configureCrud() inherits its default from (see FormThemeProviderInterface for the extension point bundles implement to reach this).
    #[\Override]
    public function configureCrud(): Crud
    {
        $crud = Crud::new()
            // How many rows a list shows, an admin's own choice when they made one (see PaginatorPageSize) - EasyAdmin only knows a fixed size, set here for every CRUD at once, and the overridden paginator is where the sizes are offered
            ->setPaginatorPageSize($this->paginatorPageSize->resolve())
            ->overrideTemplate('crud/paginator', '@c975LUi/management/paginator.html.twig');

        foreach ($this->formThemeRegistry->all() as $formTheme) {
            $crud->addFormTheme($formTheme);
        }

        return $crud;
    }

    // Each bundle providing EasyAdmin/Stimulus controllers contributes its controllers-admin.js via BundleScriptAdminProviderInterface (c975L/UiBundle) - each one starts its own independent Stimulus app. Each entry also needs a matching 'entrypoint' => true line in the app's importmap.php.
    #[\Override]
    public function configureAssets(): Assets
    {
        $assets = Assets::new()
            ->addJsFile(Asset::fromEasyAdminAssetPackage('field-text-editor.js'))
            ->addCssFile(Asset::fromEasyAdminAssetPackage('field-text-editor.css'));

        foreach ($this->scriptAdminRegistry->all() as $script) {
            $assets->addAssetMapperEntry($script);
        }

        foreach ($this->managementStylesheets() as $stylesheet) {
            $assets->addCssFile($stylesheet);
        }

        // The guided-project panel has to survive the page loads a project walks the user through, so its mount element goes on every admin page, not just the dashboard - EasyAdmin renders this on all of them (see its layout.html.twig), which spares an override of that layout
        $assets->addHtmlContentToBody($this->guidedProjectMountBuilder->getHtml());

        return $assets;
    }

    // In dev, keeps each bundle's stylesheet separate for instant reload on every CSS edit; in prod, links to the single file compiled by StylesheetCacheWarmer (c975L/UiBundle) instead. That file is written outside any asset-manifest build step, so asset() has no way to know a later warmup changed it - and it sits under /bundles/build/, which the sites' .htaccess serves "immutable" for a year, so an admin's browser would otherwise keep the stylesheet it first loaded whatever ships afterwards. Its own mtime is appended as a cache-busting query param instead, exactly as UiBundle's StylesheetExtension does for the front-office site.css. Falls back to the per-bundle list when that compiled file doesn't exist yet (the first request after a deploy, before cache:warmup has run) rather than linking a 404 and losing every back-office style at once
    // @return string[]
    private function managementStylesheets(): array
    {
        $compiledMtime = $this->debug ? false : @filemtime($this->projectDir . '/public/bundles/build/admin.css');
        if (false !== $compiledMtime) {
            return ['bundles/build/admin.css?v=' . $compiledMtime];
        }

        return array_map($this->addCacheBustingParam(...), $this->stylesheetManagementRegistry->all());
    }

    // Same reasoning for the per-bundle files: assets:install copies them into public/ at a path that never changes either, and the sites serve text/css with a one-year expiry, so a back-office CSS fix stays invisible until a hard reload. An absolute URL (a CDN stylesheet) has no local file and is returned untouched
    private function addCacheBustingParam(string $stylesheet): string
    {
        $mtime = @filemtime($this->projectDir . '/public/' . $stylesheet);

        return false === $mtime ? $stylesheet : $stylesheet . '?v=' . $mtime;
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('label.dashboard', 'fa fa-home')->setPermission(BackOfficeAccessVoter::ACCESS);

        // Menu from bundles, grouped by section and sorted alphabetically
        yield from $this->menuBuilder->getMenuItems();

        yield MenuItem::section('label.user');
        yield MenuItem::linkToLogout('label.signout', 'fa fa-exit');

        // "Made by" logo, shown at the very bottom of the sidebar, only when both configs are filled
        $madeByLogo = $this->configService->get('site-made-by-logo');
        $madeByUrl = $this->configService->get('site-made-by-url');
        if ($madeByLogo && $madeByUrl) {
            $madeByLabel = htmlspecialchars($this->translator->trans('label.made_by', [], 'site'), ENT_QUOTES);
            // Through the asset packages: the label is raw HTML, so a relative path would resolve against /management/
            yield MenuItem::linkToUrl(
                sprintf(
                    '<span>%s</span><img src="%s" alt="" class="config-made-by-logo">',
                    $madeByLabel,
                    htmlspecialchars($this->packages->getUrl($madeByLogo), ENT_QUOTES),
                ),
                null,
                $madeByUrl,
            )->setLinkTarget('_blank')->setCssClass('menu-item-made-by');
        }
    }
}
