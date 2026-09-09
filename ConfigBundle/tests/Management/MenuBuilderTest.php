<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\MenuBuilder;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuBuilderTest extends TestCase
{
    // Builds a MenuProviderInterface double for a given section/menus/links
    private function createProvider(array $section, array $menus, array $links = []): MenuProviderInterface
    {
        $provider = $this->createStub(MenuProviderInterface::class);
        $provider->method('getMenuSection')->willReturn($section);
        $provider->method('getMenus')->willReturn($menus);
        $provider->method('getLinks')->willReturn($links);

        return $provider;
    }

    // Translator double that returns the translation key untouched, so alphabetical sorting stays predictable
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return $translator;
    }

    private function createConfigService(string $role = 'ROLE_ADMIN'): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturn($role);

        return $service;
    }

    // Real route names aren't registered in a unit test - stands in for the plain Symfony router (see MenuBuilder, uses generate() instead of EasyAdmin's own AdminUrlGenerator so a link to a route outside the dashboard resolves to its real path, not "/management?routeName=..."). The fake "https://example.test/" prefix for an ABSOLUTE_URL request (vs a bare "/" for the default ABSOLUTE_PATH) lets tests tell the two apart without needing a real router/request context.
    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH) => (UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://example.test/' : '/') . $name
        );

        return $generator;
    }

    // The labels of a submenu's own items, every section being drawn as one (see MenuBuilder::getMenuItems())
    private function subItemLabels(MenuItemInterface $item): array
    {
        return array_map(static fn ($subItem) => $subItem->getLabel()->getMessage(), $item->getAsDto()->getSubItems());
    }

    public function testGetMenusSortsAlphabeticallyByTranslatedLabel(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'zebra' => ['controller' => 'ZebraController', 'label' => 'label.zebra', 'translation_domain' => 'config', 'icon' => 'fa fa-z'],
            'apple' => ['controller' => 'AppleController', 'label' => 'label.apple', 'translation_domain' => 'config', 'icon' => 'fa fa-a'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $menus = $builder->getMenus();

        $this->assertSame(['apple', 'zebra'], array_keys($menus));
    }

    // getOrderedMenus() must match the sidebar's own visual order (see getMenuItems()): every essential item first (section order, alphabetical within a section), then every advanced item grouped together at the end - not getMenus()'s plain alphabetical merge across everything
    public function testGetOrderedMenusPutsEveryAdvancedItemAfterEveryEssentialItem(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'zebra' => ['controller' => 'ZebraController', 'label' => 'label.zebra', 'translation_domain' => 'config', 'icon' => 'fa fa-z'],
            'redirect' => ['controller' => 'RedirectController', 'label' => 'label.redirects', 'translation_domain' => 'config', 'icon' => 'fa fa-r', 'tier' => 'advanced'],
            'apple' => ['controller' => 'AppleController', 'label' => 'label.apple', 'translation_domain' => 'config', 'icon' => 'fa fa-a'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $menus = $builder->getOrderedMenus();

        $this->assertSame(['apple', 'zebra', 'redirect'], array_keys($menus));
    }

    // A whole section opting into 'advanced' via getMenuSection() (rather than a per-item 'tier') still lands after every essential item, same as getMenuItems()'s own submenu grouping
    public function testGetOrderedMenusHonorsASectionsOwnAdvancedTierDefault(): void
    {
        $essential = $this->createProvider(
            ['label' => 'label.essential', 'translation_domain' => 'site'],
            ['config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog']],
        );
        $advanced = $this->createProvider(
            ['label' => 'label.seo', 'translation_domain' => 'ui', 'tier' => 'advanced'],
            ['seo' => ['controller' => 'SeoCrudController', 'label' => 'label.seo_settings', 'translation_domain' => 'ui', 'icon' => 'fa fa-search']],
        );
        $builder = new MenuBuilder([$essential, $advanced], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $this->assertSame(['config', 'seo'], array_keys($builder->getOrderedMenus()));
    }

    public function testGetLinksMergesAndSortsAcrossProviders(): void
    {
        $providerA = $this->createProvider(
            ['label' => 'label.management', 'translation_domain' => 'site'],
            [],
            ['zzz' => ['label' => 'label.zzz', 'name' => 'zzz_route', 'translation_domain' => 'config', 'icon' => 'fa fa-z']],
        );
        $providerB = $this->createProvider(
            ['label' => 'label.management', 'translation_domain' => 'site'],
            [],
            ['aaa' => ['label' => 'label.aaa', 'name' => 'aaa_route', 'translation_domain' => 'config', 'icon' => 'fa fa-a']],
        );
        $builder = new MenuBuilder([$providerA, $providerB], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $this->assertSame(['aaa', 'zzz'], array_keys($builder->getLinks()));
    }

    public function testGetMenuItemsYieldsOneSubmenuPerGroupAndAppliesAdminPermission(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService('ROLE_SUPER_ADMIN'), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertCount(1, $items);
        $this->assertInstanceOf(MenuItemInterface::class, $items[0]);
        $sectionDto = $items[0]->getAsDto();
        $this->assertSame('label.management', $sectionDto->getLabel()->getMessage());

        $itemDto = $sectionDto->getSubItems()[0];
        $this->assertSame('label.config', $itemDto->getLabel()->getMessage());
        $this->assertSame('ROLE_SUPER_ADMIN', $itemDto->getPermission());
    }

    // Sections come out of a tagged iterator, i.e. in bundle registration order, which would otherwise put a bundle installed later at the bottom of the sidebar: the shared "management" section stays first and the rest sorts alphabetically by translated label
    public function testGetMenuItemsSortsSectionsWithManagementFirst(): void
    {
        $zebra = $this->createProvider(['label' => 'label.zebra', 'translation_domain' => 'config'], [
            'zebra' => ['controller' => 'ZebraController', 'label' => 'label.zebra', 'translation_domain' => 'config', 'icon' => 'fa fa-z'],
        ]);
        $management = $this->createProvider(['label' => 'label.management', 'translation_domain' => 'site'], [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ]);
        $apple = $this->createProvider(['label' => 'label.apple', 'translation_domain' => 'config'], [
            'apple' => ['controller' => 'AppleController', 'label' => 'label.apple', 'translation_domain' => 'config', 'icon' => 'fa fa-a'],
        ]);
        $builder = new MenuBuilder([$zebra, $management, $apple], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $labels = array_map(static fn (MenuItemInterface $item) => $item->getAsDto()->getLabel()->getMessage(), iterator_to_array($builder->getMenuItems(), false));

        $this->assertSame(['label.management', 'label.apple', 'label.zebra'], $labels);
    }

    // Every section collapses so the sidebar stays short, EasyAdmin opening the one holding the current page on its own - except the shared "management" one, used daily enough to stay permanently open and not collapsible
    public function testGetMenuItemsKeepsOnlyTheManagementSubmenuPermanentlyOpen(): void
    {
        $management = $this->createProvider(['label' => 'label.management', 'translation_domain' => 'site'], [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ]);
        $shop = $this->createProvider(['label' => 'label.shop', 'translation_domain' => 'shop'], [
            'product' => ['controller' => 'ProductCrudController', 'label' => 'label.products', 'translation_domain' => 'shop', 'icon' => 'fa fa-tag'],
        ]);
        $builder = new MenuBuilder([$management, $shop], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertTrue($items[0]->getAsDto()->keepsOpen());
        $this->assertFalse($items[1]->getAsDto()->keepsOpen());
    }

    // getOrderedMenus() feeds the onboarding tour, which highlights a step by matching the sidebar's own href: a link staying inside the admin is drawn among its section's entries, so it has to be walked there too rather than after every menu
    public function testGetOrderedMenusWalksAnInternalLinkAmongItsSectionsEntries(): void
    {
        $provider = $this->createProvider(
            ['label' => 'label.management', 'translation_domain' => 'site'],
            ['zebra' => ['controller' => 'ZebraController', 'label' => 'label.zebra', 'translation_domain' => 'config', 'icon' => 'fa fa-z']],
            [
                'health' => ['name' => 'health_route', 'label' => 'label.health', 'translation_domain' => 'config', 'icon' => 'fa fa-h'],
                'site' => ['name' => 'site_route', 'label' => 'label.site', 'translation_domain' => 'config', 'icon' => 'fa fa-s', 'target' => '_blank'],
            ],
        );
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        // "label.site" leaves the admin, so it belongs to the "Liens" section and not here
        $this->assertSame(['health', 'zebra'], array_keys($builder->getOrderedMenus()));
    }

    // A section's own 'advanced' default applies to its menus alone (see getMenuSection()): two links a provider contributes without a tier of their own must be grouped the same way, whether or not they name a target
    public function testASectionsAdvancedDefaultDoesNotDragItsLinksAlong(): void
    {
        $provider = $this->createProvider(
            ['label' => 'label.seo', 'translation_domain' => 'ui', 'tier' => 'advanced'],
            ['seo' => ['controller' => 'SeoCrudController', 'label' => 'label.seo_settings', 'translation_domain' => 'ui', 'icon' => 'fa fa-search']],
            ['docs' => ['name' => 'docs_route', 'label' => 'label.docs', 'translation_domain' => 'ui', 'icon' => 'fa fa-book']],
        );
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        // The link stays essential and is drawn in its section, the menu alone joining the "Avancé" submenu
        $this->assertSame(['docs', 'seo'], array_keys($builder->getOrderedMenus()));
    }

    // getOrderedMenus() feeds the onboarding tour, so it has to walk the same section order the sidebar draws (see getMenuItems() above), not the providers' registration order
    public function testGetOrderedMenusFollowsTheSameSectionOrderAsTheSidebar(): void
    {
        $zebra = $this->createProvider(['label' => 'label.zebra', 'translation_domain' => 'config'], [
            'zebra' => ['controller' => 'ZebraController', 'label' => 'label.zebra', 'translation_domain' => 'config', 'icon' => 'fa fa-z'],
        ]);
        $management = $this->createProvider(['label' => 'label.management', 'translation_domain' => 'site'], [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ]);
        $builder = new MenuBuilder([$zebra, $management], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $this->assertSame(['config', 'zebra'], array_keys($builder->getOrderedMenus()));
    }

    // An entry naming the bar its own screen states, rather than taking the admin default: a media library or a redirects list an editor is meant to reach would be missing from their sidebar otherwise (see MenuProviderInterface::getMenus())
    public function testGetMenuItemsAppliesTheRoleAnEntryNamesOverTheAdminDefault(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'media' => ['controller' => 'MediaCrudController', 'label' => 'label.media_library', 'translation_domain' => 'ui', 'icon' => 'fas fa-photo-film', 'role' => 'ROLE_EDITOR'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService('ROLE_ADMIN'), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertSame('ROLE_EDITOR', $items[0]->getAsDto()->getSubItems()[0]->getPermission());
    }

    // EasyAdmin only falls back to "index" on its own for a CRUD controller, so an entry pointing at a plain #[AdminRoute] screen would resolve to no route at all if the action were left unset (see MenuProviderInterface::getMenus())
    public function testGetMenuItemsNamesTheActionEachEntryOpens(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
            'overview' => ['controller' => 'OverviewController', 'label' => 'label.overview', 'translation_domain' => 'config', 'icon' => 'fa fa-list', 'action' => 'show'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        // [0] is the section submenu, holding its two entries in alphabetical order
        $items = iterator_to_array($builder->getMenuItems(), false);

        $subItems = $items[0]->getAsDto()->getSubItems();
        $this->assertSame(Action::INDEX, $subItems[0]->getRouteParameters()[EA::CRUD_ACTION]);
        $this->assertSame('show', $subItems[1]->getRouteParameters()[EA::CRUD_ACTION]);
    }

    // A link naming a target leaves the back office, and those are what the "Liens" section gathers
    public function testGetMenuItemsOnlyAppendsALinksSectionWhenLinksLeavingTheAdminExist(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $providerWithoutLinks = $this->createProvider($section, []);
        $builderWithoutLinks = new MenuBuilder([$providerWithoutLinks], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        // A section contributing no items at all is not drawn: an empty submenu would render as nothing anyway
        $this->assertCount(0, iterator_to_array($builderWithoutLinks->getMenuItems(), false));

        $providerWithLinks = $this->createProvider($section, [], ['site' => [
            'label' => 'label.site_link',
            'url' => 'https://example.test/',
            'translation_domain' => 'config',
            'icon' => 'fa fa-globe',
            'target' => '_blank',
        ]]);
        $builderWithLinks = new MenuBuilder([$providerWithLinks], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builderWithLinks->getMenuItems(), false);

        // The provider contributes no menu item, so only the "links" section header and its one link are drawn
        $this->assertCount(2, $items);
        $this->assertSame('label.links', $items[0]->getAsDto()->getLabel()->getMessage());
        $this->assertSame('label.site_link', $items[1]->getAsDto()->getLabel()->getMessage());
    }

    // A link naming no target opens a back-office screen that simply has no CRUD of its own (a health check, an import): it belongs with the entries of the bundle contributing it, sorted among them by label, rather than in a "Liens" section away from them
    public function testGetMenuItemsDrawsALinkStayingInTheAdminInsideItsOwnSection(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ], [
            'whatsnew' => ['label' => 'label.whatsnew', 'name' => 'management_whatsnew_index', 'translation_domain' => 'config', 'icon' => 'fa fa-bullhorn'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertCount(1, $items);
        $this->assertSame(['label.config', 'label.whatsnew'], $this->subItemLabels($items[0]));
    }

    // A link can opt into the same collapsed submenu as an advanced CRUD item - and if every link does, the "Liens" section header is not yielded at all rather than sitting above nothing
    public function testGetMenuItemsMovesAdvancedLinksIntoTheAdvancedSubmenu(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'legal' => [
                'label' => 'label.legal_models',
                'name' => 'management_ui_legal_models',
                'translation_domain' => 'site',
                'icon' => 'fas fa-scale-balanced',
                'tier' => 'advanced',
            ],
            'site' => [
                'label' => 'label.site_link',
                'url' => 'https://example.test/',
                'translation_domain' => 'config',
                'icon' => 'fa fa-globe',
                'target' => '_blank',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        // "Avancé" submenu holding the advanced link, then the "Liens" section and its one link
        $this->assertCount(3, $items);
        $this->assertSame('label.menu_advanced', $items[0]->getAsDto()->getLabel()->getMessage());
        $this->assertSame(['label.legal_models'], $this->subItemLabels($items[0]));
        $this->assertSame('label.links', $items[1]->getAsDto()->getLabel()->getMessage());
        $this->assertSame('label.site_link', $items[2]->getAsDto()->getLabel()->getMessage());
    }

    public function testGetMenuItemsDropsTheLinksSectionWhenEveryLinkIsAdvanced(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'legal' => [
                'label' => 'label.legal_models',
                'name' => 'management_ui_legal_models',
                'translation_domain' => 'site',
                'icon' => 'fas fa-scale-balanced',
                'tier' => 'advanced',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertCount(1, $items);
        $this->assertSame('label.menu_advanced', $items[0]->getAsDto()->getLabel()->getMessage());
    }

    public function testGetMenuItemsAppliesLinkRoleWhenProvidedAndLeavesItUnsetOtherwise(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'media' => [
                'label' => 'label.media',
                'name' => 'management_media_index',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-photo-film',
                'role' => 'ROLE_EDITOR',
            ],
            'shop' => [
                'label' => 'label.shop',
                'name' => 'shop_index',
                'translation_domain' => 'shop',
                'icon' => '',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        // Both links stay in the admin, so they are drawn in the provider's own section, sorted alphabetically: media before shop
        $subItems = $items[0]->getAsDto()->getSubItems();
        $this->assertSame('ROLE_EDITOR', $subItems[0]->getPermission());
        $this->assertNull($subItems[1]->getPermission());
    }

    // A link's URL must come from the plain router (generate()), not EasyAdmin's own AdminUrlGenerator - the latter assumes the route is one of the dashboard's own registered actions and wraps it as "/management?routeName=...", which is wrong for a route outside the dashboard entirely (e.g. a consuming app's own public page) - regression test for exactly that bug
    public function testGetMenuItemsResolvesLinkUrlThroughThePlainRouter(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'showcase' => [
                'label' => 'label.block_showcase',
                'name' => 'app_block_showcase_index',
                'translation_domain' => 'messages',
                'icon' => 'fas fa-shapes',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertSame('/app_block_showcase_index', $items[0]->getAsDto()->getSubItems()[0]->getLinkUrl());
    }

    // Optional per-link "target" (e.g. '_blank' for a link leaving the admin) - unset by default, same opt-in shape as "role". A "target" link also gets a full absolute URL (scheme+host), not just a path, generated fresh from the current request each time (never a hardcoded domain, so it stays correct across dev/staging/prod or any future domain change) - it's meant to stand on its own once opened in a new tab, unlike a same-tab link staying relative to the current page.
    public function testGetMenuItemsAppliesLinkTargetAndAbsoluteUrlWhenProvidedAndLeavesBothUnsetOtherwise(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'showcase' => [
                'label' => 'label.block_showcase',
                'name' => 'app_block_showcase_index',
                'translation_domain' => 'messages',
                'icon' => 'fas fa-shapes',
                'target' => '_blank',
            ],
            'shop' => [
                'label' => 'label.shop',
                'name' => 'shop_index',
                'translation_domain' => 'shop',
                'icon' => '',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        // The showcase link names a target, so it leaves the admin for the "Liens" section; the shop one names none and stays in the provider's own section. MenuItemDto's own default target (not set by MenuBuilder) is '_self', not null
        $shopDto = $items[0]->getAsDto()->getSubItems()[0];
        $this->assertSame('_self', $shopDto->getLinkTarget());
        $this->assertSame('/shop_index', $shopDto->getLinkUrl());

        $this->assertSame('label.links', $items[1]->getAsDto()->getLabel()->getMessage());
        $this->assertSame('_blank', $items[2]->getAsDto()->getLinkTarget());
        $this->assertSame('https://example.test/app_block_showcase_index', $items[2]->getAsDto()->getLinkUrl());
    }

    // A section opting into 'advanced' (see MenuProviderInterface::getMenuSection()) doesn't get its own top-level section header - its items are collected into one collapsed "Avancé" submenu instead, appended after every essential section
    public function testGetMenuItemsGroupsAdvancedTierSectionsIntoOneCollapsedSubmenu(): void
    {
        $essential = $this->createProvider(
            ['label' => 'label.essential', 'translation_domain' => 'site'],
            ['config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog']],
        );
        $advanced = $this->createProvider(
            ['label' => 'label.seo', 'translation_domain' => 'ui', 'tier' => 'advanced'],
            ['seo' => ['controller' => 'SeoCrudController', 'label' => 'label.seo_settings', 'translation_domain' => 'ui', 'icon' => 'fa fa-search']],
        );
        $builder = new MenuBuilder([$essential, $advanced], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        // Essential section submenu holding its item, then the "Avancé" one (no separate "seo" section)
        $this->assertCount(2, $items);
        $this->assertSame('label.essential', $items[0]->getAsDto()->getLabel()->getMessage());
        $this->assertSame(['label.config'], $this->subItemLabels($items[0]));

        $submenuDto = $items[1]->getAsDto();
        $this->assertSame('label.menu_advanced', $submenuDto->getLabel()->getMessage());
        $this->assertCount(1, $submenuDto->getSubItems());
        $this->assertSame('label.seo_settings', $submenuDto->getSubItems()[0]->getLabel()->getMessage());
    }

    // An item's own 'tier' must move just that item, not the rest of a shared section
    public function testGetMenuItemsMovesOnlyTheItemsThatOptInWithinASharedSection(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $providerA = $this->createProvider($section, [
            'page' => ['controller' => 'PageCrudController', 'label' => 'label.pages', 'translation_domain' => 'site', 'icon' => 'fa fa-file'],
            'redirect' => ['controller' => 'RedirectCrudController', 'label' => 'label.redirects', 'translation_domain' => 'site', 'icon' => 'fa fa-arrow-right', 'tier' => 'advanced'],
        ]);
        $providerB = $this->createProvider($section, [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ]);
        $builder = new MenuBuilder([$providerA, $providerB], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        // One section submenu holding its 2 essential items (config, page - alphabetical), then the "Avancé" one holding "redirect" alone
        $this->assertCount(2, $items);
        $this->assertSame('label.management', $items[0]->getAsDto()->getLabel()->getMessage());
        $this->assertSame(['label.config', 'label.pages'], $this->subItemLabels($items[0]));

        $submenuDto = $items[1]->getAsDto();
        $this->assertSame('label.menu_advanced', $submenuDto->getLabel()->getMessage());
        $this->assertCount(1, $submenuDto->getSubItems());
        $this->assertSame('label.redirects', $submenuDto->getSubItems()[0]->getLabel()->getMessage());
    }

    // A section without a 'tier' key (or explicitly 'essential') keeps today's behavior - no submenu is created when nothing opts into 'advanced'
    public function testGetMenuItemsOmitsTheAdvancedSubmenuWhenNoSectionOptsIn(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [
            'config' => ['controller' => 'ConfigCrudController', 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertCount(1, $items);
        foreach ($items as $item) {
            $this->assertNotSame('label.menu_advanced', $item->getAsDto()->getLabel()?->getMessage());
        }
    }

    // An optional "label_parameters" array (e.g. ['%name%' => $siteName]) is passed through to the label's TranslatableMessage, so a link's translated label can embed a runtime value (e.g. "Site : %name%")
    public function testGetMenuItemsPassesLabelParametersThroughToTheTranslatableMessage(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'site' => [
                'label' => 'label.site_link',
                'label_parameters' => ['%name%' => 'My Site'],
                'url' => 'https://example.test/',
                'translation_domain' => 'config',
                'icon' => 'fa fa-globe',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertSame(['%name%' => 'My Site'], $items[0]->getAsDto()->getSubItems()[0]->getLabel()->getParameters());
    }

    // A "pinned" link (e.g. a "visit the site" link) always sorts after every non-pinned link, even one that would otherwise sort first alphabetically
    public function testGetLinksSortsPinnedLinksAfterNonPinnedOnesRegardlessOfLabel(): void
    {
        $provider = $this->createProvider(
            ['label' => 'label.management', 'translation_domain' => 'site'],
            [],
            [
                'site' => ['label' => 'label.aaa_site', 'url' => 'https://example.test/', 'translation_domain' => 'config', 'icon' => 'fa fa-globe', 'pinned' => true],
                'whatsnew' => ['label' => 'label.zzz_whatsnew', 'name' => 'management_whatsnew_index', 'translation_domain' => 'config', 'icon' => 'fa fa-bullhorn'],
            ],
        );
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $this->assertSame(['whatsnew', 'site'], array_keys($builder->getLinks()));
    }

    // An explicit "url" (a literal, already-absolute URL) is used as-is, bypassing route resolution entirely - for a provider that wants a link fixed/directly editable rather than derived from a route (an app's own MenuProvider pinning a showcase link to the production domain on purpose, rather than to the routes of the site rendering it)
    public function testGetMenuItemsUsesAnExplicitUrlAsIsWithoutRouteResolution(): void
    {
        $section = ['label' => 'label.management', 'translation_domain' => 'site'];
        $provider = $this->createProvider($section, [], [
            'showcase' => [
                'label' => 'label.block_showcase',
                'url' => 'https://example.com/vitrine-blocks',
                'translation_domain' => 'messages',
                'icon' => 'fas fa-shapes',
                'target' => '_blank',
            ],
        ]);
        $builder = new MenuBuilder([$provider], $this->createConfigService(), $this->createTranslator(), $this->createUrlGenerator());

        $items = iterator_to_array($builder->getMenuItems(), false);

        $this->assertSame('https://example.com/vitrine-blocks', $items[1]->getAsDto()->getLinkUrl());
        $this->assertSame('label.links', $items[0]->getAsDto()->getLabel()->getMessage());
    }
}
