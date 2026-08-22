<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FontCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuProviderTest extends TestCase
{
    private function createConfigService(?string $showcaseUrl = null): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['site-role-admin', 'ROLE_ADMIN'],
            ['site-role-editor', 'ROLE_EDITOR'],
            ['ui-block-showcase-url', $showcaseUrl],
        ]);

        return $configService;
    }

    private function createTranslator(string $suffix = 'AI Agent'): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['label.ai_assistant_menu_suffix', [], 'ui', $suffix],
        ]);

        return $translator;
    }

    // Matches ConfigBundle's/SiteBundle's section value so a future CRUD entry here merges into the same group
    // Three of the five screens answer an editor: without the key the entry takes the admin default and goes missing from their sidebar, with the tour step that walks to it (see MenuProviderInterface::getMenus())
    public function testTheEditorScreensNameTheirOwnRoleAndTheOthersTakeTheAdminDefault(): void
    {
        $menus = new MenuProvider($this->createConfigService(), $this->createTranslator())->getMenus();

        $this->assertSame('ROLE_EDITOR', $menus['media']['role']);
        $this->assertSame('ROLE_EDITOR', $menus['font']['role']);
        $this->assertSame('ROLE_EDITOR', $menus['site_graphic']['role']);
        // An action key and what a mail says are the admin's, so these two say nothing and fall back on the default
        $this->assertArrayNotHasKey('role', $menus['form']);
        $this->assertArrayNotHasKey('role', $menus['email_template']);
    }

    public function testGetMenuSectionMatchesTheSharedManagementSection(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator());

        $this->assertSame(['label' => 'label.management', 'translation_domain' => 'site'], $provider->getMenuSection());
    }

    // This bundle's own CRUD entries, which SiteBundle used to declare on its behalf - a site without SiteBundle got none of them
    public function testGetMenusReturnsThisBundlesOwnCrudEntries(): void
    {
        $menus = new MenuProvider($this->createConfigService(), $this->createTranslator())->getMenus();

        $this->assertSame(['media', 'form', 'email_template', 'font', 'site_graphic'], array_keys($menus));
        $this->assertSame(MediaCrudController::class, $menus['media']['controller']);
        $this->assertSame(FormCrudController::class, $menus['form']['controller']);
        $this->assertSame(EmailTemplateCrudController::class, $menus['email_template']['controller']);
        $this->assertSame(FontCrudController::class, $menus['font']['controller']);
        $this->assertSame(SiteGraphicCrudController::class, $menus['site_graphic']['controller']);

        foreach ($menus as $key => $menu) {
            $this->assertSame('ui', $menu['translation_domain'], $key . ' should carry this bundle\'s own domain');
        }
    }

    // The media library is a day-to-day screen and stays at the top level; forms, email templates, fonts and the site graphics are set up once, so they belong in MenuBuilder's collapsed "Advanced" submenu
    public function testOnlyTheSetupOnceScreensAreTieredAsAdvanced(): void
    {
        $menus = new MenuProvider($this->createConfigService(), $this->createTranslator())->getMenus();

        $this->assertArrayNotHasKey('tier', $menus['media']);

        foreach (['form', 'email_template', 'font', 'site_graphic'] as $advanced) {
            $this->assertSame('advanced', $menus[$advanced]['tier'], $advanced . ' should be advanced');
        }
    }

    // Every entry's 'description' reuses the exact same key as its own screen's explanatory text - one text, not a separate onboarding-only string
    public function testGetMenusDescriptionReusesEachScreensOwnExplanatoryText(): void
    {
        $menus = new MenuProvider($this->createConfigService(), $this->createTranslator())->getMenus();

        $this->assertSame('label.info_media', $menus['media']['description']);
        $this->assertSame('label.info_form', $menus['form']['description']);
        $this->assertSame('label.info_email_template', $menus['email_template']['description']);
        $this->assertSame('label.info_font', $menus['font']['description']);
        $this->assertSame('label.info_site_graphic', $menus['site_graphic']['description']);
    }

    // An external url, not a route name, the showcase living on its own site
    public function testGetLinksReturnsTheBlockShowcaseLinkFromTheConfig(): void
    {
        $provider = new MenuProvider($this->createConfigService('https://example.org/pages/blocks'), $this->createTranslator());

        $links = $provider->getLinks();

        $this->assertCount(3, $links);
        $this->assertSame('label.block_showcase', $links['block_showcase']['label']);
        $this->assertSame('ui', $links['block_showcase']['translation_domain']);
        $this->assertSame('https://example.org/pages/blocks', $links['block_showcase']['url']);
        $this->assertSame('_blank', $links['block_showcase']['target']);
        $this->assertSame('label.block_showcase_help', $links['block_showcase']['description']);
    }

    // The one non-CRUD screen of this bundle: customizing a legal model is not an entity CRUD, it edits one block's delta against templates the bundle ships (see LegalModelController)
    public function testGetLinksContributesTheLegalModelsScreen(): void
    {
        $links = new MenuProvider($this->createConfigService(), $this->createTranslator())->getLinks();

        $this->assertSame(LegalModelController::INDEX_ROUTE, $links['legal_models']['name']);
        $this->assertSame('ui', $links['legal_models']['translation_domain']);
        // Gated on the same role the screen itself demands, so it never shows to someone it would 403 on
        $this->assertSame('ROLE_EDITOR', $links['legal_models']['role']);
        // Set up once, then revisited rarely - it belongs in the collapsed "Avancé" submenu
        $this->assertSame('advanced', $links['legal_models']['tier']);
    }

    // An app installed before the key existed (its configs.json not reloaded yet) still gets a working link
    public function testGetLinksFallsBackOnTheEcosystemShowcaseWhenTheConfigIsEmpty(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator());

        $this->assertSame(MenuProvider::BLOCK_SHOWCASE_URL, $provider->getLinks()['block_showcase']['url']);
        $this->assertSame('https://bundles.975l.com/pages/blocks', MenuProvider::BLOCK_SHOWCASE_URL);
    }

    // The constant is the config entry's default repeated in PHP - the two drifting apart would have a fresh install and a not-yet-reloaded one point at different addresses
    public function testTheFallbackMatchesTheConfigsJsonDefault(): void
    {
        $configs = json_decode(file_get_contents(__DIR__ . '/../../config/configs.json'), true, 512, \JSON_THROW_ON_ERROR);

        $entry = array_values(array_filter($configs, static fn (array $config): bool => 'ui-block-showcase-url' === $config['slug']));

        $this->assertCount(1, $entry, 'No "ui-block-showcase-url" entry in configs.json.');
        $this->assertSame(MenuProvider::BLOCK_SHOWCASE_URL, $entry[0]['value']);
    }

    // 'role' matches the page's own minimum bar, a plain editor being unable to act on either section
    public function testGetLinksReturnsTheAiAssistantLinkWithTheHardcodedNameTranslatedSuffixAndRole(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator('AI Agent'));

        $links = $provider->getLinks();

        $this->assertSame('Donovan (AI Agent)', $links['ai_assistant']['label']);
        $this->assertSame('ui', $links['ai_assistant']['translation_domain']);
        $this->assertSame('management_ui_ai_assistant_index', $links['ai_assistant']['name']);
        $this->assertSame('ROLE_ADMIN', $links['ai_assistant']['role']);
        $this->assertSame('label.ai_assistant_subtitle', $links['ai_assistant']['description']);
    }
}
