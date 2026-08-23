<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\DashboardController;
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
use c975L\ConfigBundle\Twig\CreditsExtension;
use c975L\UiBundle\Management\PaginatorPageSize;
use c975L\UiBundle\Registry\FormThemeRegistry;
use c975L\UiBundle\Registry\ScriptAdminRegistry;
use c975L\UiBundle\Registry\StylesheetManagementRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DashboardControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private ?string $projectDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->projectDir) {
            $this->removeDirectory($this->projectDir);
            $this->projectDir = null;
        }
    }

    private function createController(bool $debug, array $managementStylesheets, array $configs = [], string $guidedProjectMount = '', ?PaginatorPageSize $paginatorPageSize = null, ?EssentialActionBuilder $essentialActionBuilder = null): DashboardController
    {
        $guidedProjectMountBuilder = $this->createStub(GuidedProjectMountBuilder::class);
        $guidedProjectMountBuilder->method('getHtml')->willReturn($guidedProjectMount);

        $stylesheetManagementRegistry = $this->createStub(StylesheetManagementRegistry::class);
        $stylesheetManagementRegistry->method('all')->willReturn($managementStylesheets);

        // Both bars are always set: configureMenuItems() passes the editor one straight to setPermission(), which rejects null, and index() reads them both
        $configs += ['site-role-admin' => 'ROLE_ADMIN', 'site-role-editor' => 'ROLE_EDITOR'];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(fn (string $key) => $configs[$key] ?? null);

        // Echoes the key back, so a test can tell which label the menu item was built from
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        // Stands in for the real asset packages, which turn a logical path into its digested public URL
        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturnCallback(
            fn (string $path) => str_starts_with($path, 'http') ? $path : '/assets/' . $path . '?digest'
        );

        return new DashboardController(
            $this->createStub(MenuBuilder::class),
            $this->createStub(WhatsNewBuilder::class),
            $this->createStub(AlertBuilder::class),
            $this->createStub(ShortcutBuilder::class),
            $essentialActionBuilder ?? $this->createStub(EssentialActionBuilder::class),
            $this->createStub(DashboardWidgetBuilder::class),
            $this->createStub(OnboardingStepBuilder::class),
            $this->createStub(GuidedProjectBuilder::class),
            $guidedProjectMountBuilder,
            $configService,
            new CreditsExtension($configService),
            $this->createStub(ScriptAdminRegistry::class),
            $stylesheetManagementRegistry,
            $this->createStub(FormThemeRegistry::class),
            $paginatorPageSize ?? new PaginatorPageSize(new RequestStack()),
            $translator,
            $packages,
            $debug,
            $this->projectDir ?? sys_get_temp_dir(),
        );
    }

    // The controller stamps each stylesheet with its own mtime, so the files have to actually exist somewhere - a throwaway project dir holding just the ones a test cares about
    private function createProjectDir(array $publicFiles): string
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-dashboard-' . uniqid('', true);
        foreach ($publicFiles as $path) {
            $fullPath = $this->projectDir . '/public/' . $path;
            @mkdir(\dirname($fullPath), 0777, true);
            file_put_contents($fullPath, '/* css */');
        }

        return $this->projectDir;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }

    private function getMadeByLabel(DashboardController $controller): ?string
    {
        foreach ($controller->configureMenuItems() as $item) {
            $label = $item->getAsDto()->getLabel();
            if (is_string($label) && preg_match('/<span>([^<]*)<\/span>/', $label, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    private function getMadeByLogoSrc(DashboardController $controller): ?string
    {
        foreach ($controller->configureMenuItems() as $item) {
            $label = $item->getAsDto()->getLabel();
            if (is_string($label) && preg_match('/<img src="([^"]*)"/', $label, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    // In dev, each bundle-contributed management stylesheet is added separately, for instant reload on every CSS edit
    // The dashboard opens to anyone standing on the back-office floor, its blocks answering for what they may see - and the floor is a voter attribute, not one named bar: no role_hierarchy is shipped, so an account holding only the admin one passes no site-role-editor gate (see BackOfficeAccessVoter)
    public function testIndexOpensToAnyoneOnTheBackOfficeFloor(): void
    {
        $this->assertSame(200, $this->renderIndexFor([BackOfficeAccessVoter::ACCESS])['status']);
    }

    // The checklist walks the site's own setup, screen by screen, and an editor has the role for none of them - it is not built for them rather than gated action by action
    public function testIndexBuildsNoEssentialActionForAnEditor(): void
    {
        $editor = $this->renderIndexFor([BackOfficeAccessVoter::ACCESS]);
        $admin = $this->renderIndexFor([BackOfficeAccessVoter::ACCESS, 'ROLE_ADMIN']);

        $this->assertSame([], $editor['context']['essentialActions']);
        $this->assertSame([], $editor['context']['essentialActionsProgress']);
        $this->assertSame([['slug' => 'site-name']], $admin['context']['essentialActions']);
        $this->assertSame(['done' => 1, 'total' => 3], $admin['context']['essentialActionsProgress']);
    }

    // Renders index() for a user granted exactly these attributes, and hands back the status and the variables the template was given
    private function renderIndexFor(array $granted): array
    {
        $essentialActionBuilder = $this->createStub(EssentialActionBuilder::class);
        $essentialActionBuilder->method('getActions')->willReturn([['slug' => 'site-name']]);
        $essentialActionBuilder->method('getProgress')->willReturn(['done' => 1, 'total' => 3]);

        $controller = $this->createController(false, [], [], '', null, $essentialActionBuilder);

        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute) => \in_array($attribute, $granted, true)
        );

        $context = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $template, array $parameters = []) use (&$context): string {
                $context = $parameters;

                return '<html></html>';
            }
        );

        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $checker,
            'twig' => $twig,
        ]));

        return ['status' => $controller->index()->getStatusCode(), 'context' => $context];
    }

    public function testConfigureAssetsAddsEachManagementStylesheetSeparatelyInDebug(): void
    {
        $this->createProjectDir(['bundles/c975lconfig/css/management.min.css']);
        $controller = $this->createController(true, ['bundles/c975lconfig/css/management.min.css']);

        $cssPaths = array_keys($controller->configureAssets()->getAsDto()->getCssAssets());

        $this->assertNotEmpty(preg_grep('#^bundles/c975lconfig/css/management\.min\.css\?v=#', $cssPaths));
        $this->assertEmpty(preg_grep('#^bundles/build/admin\.css#', $cssPaths));
    }

    // Outside debug, links to the single file compiled by StylesheetCacheWarmer (c975L/UiBundle) instead of the per-bundle list
    public function testConfigureAssetsAddsCompiledAdminStylesheetWhenNotDebug(): void
    {
        $projectDir = $this->createProjectDir(['bundles/build/admin.css']);
        $controller = $this->createController(false, ['bundles/c975lconfig/css/management.min.css']);

        $cssPaths = array_keys($controller->configureAssets()->getAsDto()->getCssAssets());

        $this->assertContains('bundles/build/admin.css?v=' . filemtime($projectDir . '/public/bundles/build/admin.css'), $cssPaths);
        $this->assertEmpty(preg_grep('#^bundles/c975lconfig#', $cssPaths));
    }

    // /bundles/build/ is served "immutable" for a year by the sites' .htaccess and the compiled file is written outside any asset-manifest build step - without its mtime on the url, an admin's browser keeps the stylesheet it first loaded whatever ships afterwards
    public function testCompiledAdminStylesheetCarriesItsOwnMtimeAsCacheBuster(): void
    {
        $projectDir = $this->createProjectDir(['bundles/build/admin.css']);
        $compiledPath = $projectDir . '/public/bundles/build/admin.css';
        touch($compiledPath, 1750000000);

        $cssPaths = array_keys($this->createController(false, [])->configureAssets()->getAsDto()->getCssAssets());

        $this->assertContains('bundles/build/admin.css?v=1750000000', $cssPaths);
    }

    // The first request after a deploy, before cache:warmup has written the compiled file - linking it anyway would 404 and lose every back-office style at once
    public function testConfigureAssetsFallsBackToThePerBundleListWhenTheCompiledFileIsMissing(): void
    {
        $this->createProjectDir(['bundles/c975lconfig/css/management.min.css']);
        $controller = $this->createController(false, ['bundles/c975lconfig/css/management.min.css']);

        $cssPaths = array_keys($controller->configureAssets()->getAsDto()->getCssAssets());

        $this->assertNotEmpty(preg_grep('#^bundles/c975lconfig/css/management\.min\.css\?v=#', $cssPaths));
        $this->assertEmpty(preg_grep('#^bundles/build/admin\.css#', $cssPaths));
    }

    // A CDN stylesheet has no local file to stat - returned untouched rather than dropped or stamped with a bogus version
    public function testAnAbsoluteStylesheetUrlIsLeftUntouched(): void
    {
        $this->createProjectDir([]);
        $controller = $this->createController(true, ['https://cdn.example.com/cookieconsent.min.css']);

        $cssPaths = array_keys($controller->configureAssets()->getAsDto()->getCssAssets());

        $this->assertContains('https://cdn.example.com/cookieconsent.min.css', $cssPaths);
    }

    // The guided-project panel has to survive the page loads a project walks the user through, so its mount element goes into the body of every admin page rather than into the dashboard template alone - EasyAdmin renders these on all of them, which spares an override of its layout
    public function testConfigureAssetsMountsTheGuidedProjectPanelOnEveryAdminPage(): void
    {
        $controller = $this->createController(true, [], [], '<div data-controller="guided-project"></div>');

        $this->assertContains('<div data-controller="guided-project"></div>', $controller->configureAssets()->getAsDto()->getBodyContents());
    }

    // The label is raw HTML, so a relative path must go through the asset packages, not /management/
    public function testMadeByLogoPathIsResolvedThroughTheAssetPackages(): void
    {
        $controller = $this->createController(false, [], [
            'site-made-by-logo' => 'images/logo-975l.svg',
            'site-made-by-url' => 'https://975l.com',
        ]);

        $this->assertSame('/assets/images/logo-975l.svg?digest', $this->getMadeByLogoSrc($controller));
    }

    // A config still holding the absolute URL used before must keep working
    // The sidebar credit follows the same wording config as the footer one: a site only running the system is "Powered by", not "Made by"
    public function testMadeByMenuItemFollowsTheWordingConfig(): void
    {
        $configs = [
            'site-made-by-logo' => 'images/logo-975l.svg',
            'site-made-by-url' => 'https://975l.com',
        ];

        $this->assertSame('label.made_by', $this->getMadeByLabel($this->createController(false, [], $configs)));
        $this->assertSame('label.powered_by', $this->getMadeByLabel($this->createController(false, [], $configs + ['made-by-wording' => 'powered'])));
    }

    public function testMadeByLogoAbsoluteUrlIsLeftUntouched(): void
    {
        $controller = $this->createController(false, [], [
            'site-made-by-logo' => 'https://975l.com/images/logo-975l.svg',
            'site-made-by-url' => 'https://975l.com',
        ]);

        $this->assertSame('https://975l.com/images/logo-975l.svg', $this->getMadeByLogoSrc($controller));
    }

    public function testNoMadeByMenuItemWhenEitherConfigIsEmpty(): void
    {
        $controller = $this->createController(false, [], ['site-made-by-logo' => 'images/logo-975l.svg']);

        $this->assertNull($this->getMadeByLogoSrc($controller));
    }

    // Every CRUD of every c975L bundle inherits its page size from here - EasyAdmin's own default (20) applies as long as no admin picked another one
    public function testConfigureCrudAppliesTheDefaultPageSize(): void
    {
        $crudDto = $this->createController(false, [])->configureCrud()->getAsDto();

        $this->assertSame(PaginatorPageSize::DEFAULT_SIZE, $crudDto->getPaginator()->getPageSize());
    }

    // The size an admin clicked in the paginator, read from the url by PaginatorPageSize
    public function testConfigureCrudAppliesThePageSizeAskedForInTheRequest(): void
    {
        $requestStack = new RequestStack([new Request(['pageSize' => '100'])]);

        $crudDto = $this->createController(false, [], [], '', new PaginatorPageSize($requestStack))->configureCrud()->getAsDto();

        $this->assertSame(100, $crudDto->getPaginator()->getPageSize());
    }

    // The sizes are offered by the overridden paginator, which is what makes the choice reachable at all - and it has to reach every CRUD, hence its place here rather than in each controller
    public function testConfigureCrudOverridesThePaginatorForEveryCrud(): void
    {
        $crudDto = $this->createController(false, [])->configureCrud()->getAsDto();

        $this->assertSame('@c975LUi/management/paginator.html.twig', $crudDto->getOverriddenTemplates()['crud/paginator'] ?? null);
    }
}
