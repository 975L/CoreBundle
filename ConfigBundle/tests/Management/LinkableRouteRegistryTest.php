<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\LinkableRouteCacheTagsInterface;
use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

class LinkableRouteRegistryTest extends TestCase
{
    private function createProvider(array $routes): LinkableRouteProviderInterface
    {
        $provider = $this->createStub(LinkableRouteProviderInterface::class);
        $provider->method('getLinkableRoutes')->willReturn($routes);

        return $provider;
    }

    // Answers each key with "key.domain", so a test tells a translated label from a literal one
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => $id . '.' . $domain
        );

        return $translator;
    }

    private function createRegistry(array $providers): LinkableRouteRegistry
    {
        return new LinkableRouteRegistry($providers, $this->createTranslator());
    }

    public function testHasAndGetReflectRoutesMergedAcrossProviders(): void
    {
        $providerA = $this->createProvider(['contact_index' => ['label' => 'label.contact', 'translation_domain' => 'contact']]);
        $providerB = $this->createProvider(['shop_index' => ['label' => 'label.shop', 'translation_domain' => 'shop']]);
        $registry = $this->createRegistry([$providerA, $providerB]);

        $this->assertTrue($registry->has('contact_index'));
        $this->assertTrue($registry->has('shop_index'));
        $this->assertSame(
            ['label' => 'label.contact', 'translation_domain' => 'contact', 'route' => 'contact_index', 'params' => [], 'locales' => null],
            $registry->get('contact_index')
        );
    }

    public function testHasReturnsFalseAndGetReturnsNullForUnknownRoute(): void
    {
        $registry = $this->createRegistry([$this->createProvider([])]);

        $this->assertFalse($registry->has('unknown_route'));
        $this->assertNull($registry->get('unknown_route'));
    }

    public function testAllReturnsEveryMergedRoute(): void
    {
        $providerA = $this->createProvider(['route-a' => ['label' => 'a']]);
        $providerB = $this->createProvider(['route-b' => ['label' => 'b']]);
        $registry = $this->createRegistry([$providerA, $providerB]);

        $this->assertSame([
            'route-a' => ['label' => 'a', 'route' => 'route-a', 'params' => [], 'translation_domain' => false, 'locales' => null],
            'route-b' => ['label' => 'b', 'route' => 'route-b', 'params' => [], 'translation_domain' => false, 'locales' => null],
        ], $registry->all());
    }

    // A menu item is written in the language being read only where its target answers in it, and an entry saying nothing is not the same as one answering "every language": the null is what tells MenuExtension to try the localised twin and fall back on it (see SiteBundle's MenuExtension::routeUrl())
    public function testAnEntryKeepsTheLanguagesItDeclaresAndIsNullWhenItDeclaresNone(): void
    {
        $registry = $this->createRegistry([$this->createProvider([
            'shop_index' => ['label' => 'label.shop', 'translation_domain' => 'shop', 'locales' => ['fr', 'en']],
            'contact_index' => ['label' => 'label.contact', 'translation_domain' => 'contact'],
        ])]);

        $this->assertSame(['fr', 'en'], $registry->get('shop_index')['locales']);
        $this->assertNull($registry->get('contact_index')['locales']);
    }

    // An entry standing for one row of a bundle's own data keys itself on that row and names what to generate its url with, its key not being a route name at all
    public function testAnEntryKeepsTheRouteAndParametersItDeclares(): void
    {
        $registry = $this->createRegistry([$this->createProvider(['gallery_category.12' => [
            'label' => 'Paysages',
            'translation_domain' => false,
            'route' => 'gallery_category',
            'params' => ['category' => 'paysages'],
        ]])]);

        $this->assertSame([
            'label' => 'Paysages',
            'translation_domain' => false,
            'route' => 'gallery_category',
            'params' => ['category' => 'paysages'],
            'locales' => null,
        ], $registry->get('gallery_category.12'));
    }

    // A key is what a menu item stores ("route:KEY"), so it has to come out of the merge exactly as the provider wrote it - a numeric one renumbered into a position would send the item to another target, or to none
    public function testANumericKeyIsKeptAsTheProviderWroteIt(): void
    {
        $registry = $this->createRegistry([
            $this->createProvider(['contact_index' => ['label' => 'label.contact', 'translation_domain' => 'contact']]),
            $this->createProvider([42 => [
                'label' => 'Paysages',
                'translation_domain' => false,
                'route' => 'gallery_category',
                'params' => ['category' => 'paysages'],
            ]]),
        ]);

        $this->assertTrue($registry->has('42'));
        $this->assertSame('gallery_category', $registry->get('42')['route']);
    }

    public function testLabelTranslatesAnEntryDeclaringADomain(): void
    {
        $registry = $this->createRegistry([$this->createProvider(['contact_index' => ['label' => 'label.contact', 'translation_domain' => 'contact']])]);

        $this->assertSame('label.contact.contact', $registry->label('contact_index'));
    }

    // A database row's own title is shown as it is - it is no translation key
    public function testLabelReturnsTheLiteralLabelOfAnEntryWithoutADomain(): void
    {
        $registry = $this->createRegistry([$this->createProvider(['gallery_category.12' => ['label' => 'Paysages', 'translation_domain' => false]])]);

        $this->assertSame('Paysages', $registry->label('gallery_category.12'));
    }

    // The select holds a row's entry among every page of the site, so it says what that row is; the rendered menu item keeps its bare title
    public function testPickerLabelPrefersTheEntrysOwnPickerLabel(): void
    {
        $registry = $this->createRegistry([$this->createProvider(['gallery_category.12' => [
            'label' => 'Paysages',
            'translation_domain' => false,
            'picker_label' => 'Galerie - Paysages',
        ]])]);

        $this->assertSame('Galerie - Paysages', $registry->pickerLabel('gallery_category.12'));
        $this->assertSame('Paysages', $registry->label('gallery_category.12'));
    }

    public function testPickerLabelFallsBackToTheLabelOfAnEntryWithoutOne(): void
    {
        $registry = $this->createRegistry([$this->createProvider(['contact_index' => ['label' => 'label.contact', 'translation_domain' => 'contact']])]);

        $this->assertSame('label.contact.contact', $registry->pickerLabel('contact_index'));
    }

    public function testPickerLabelReturnsEmptyStringForUnknownRoute(): void
    {
        $registry = $this->createRegistry([$this->createProvider([])]);

        $this->assertSame('', $registry->pickerLabel('unknown_route'));
    }

    public function testLabelReturnsEmptyStringForUnknownRoute(): void
    {
        $registry = $this->createRegistry([$this->createProvider([])]);

        $this->assertSame('', $registry->label('unknown_route'));
    }

    // Providers are only walked when the registry is actually read: one listing an entry per row of its own data queries the database to do so, and this service is instantiated on every rendered page
    public function testProvidersAreNotWalkedBeforeTheRegistryIsRead(): void
    {
        $provider = $this->createMock(LinkableRouteProviderInterface::class);
        $provider->expects($this->never())->method('getLinkableRoutes');

        $this->createRegistry([$provider]);
    }

    public function testProvidersAreWalkedOnlyOnceAcrossReads(): void
    {
        $provider = $this->createMock(LinkableRouteProviderInterface::class);
        $provider->expects($this->once())->method('getLinkableRoutes')->willReturn(['contact_index' => ['label' => 'label.contact', 'translation_domain' => 'contact']]);
        $registry = $this->createRegistry([$provider]);

        $registry->all();
        $registry->has('contact_index');
        $registry->label('contact_index');

        $this->assertNotNull($registry->get('contact_index'));
    }

    // A provider listing its rows says which tags its bundle empties when one is saved: its entries are then read once for every request sharing the pool, and a menu item pointing at one of them can be cached under the same tags
    public function testAProviderDeclaringItsTagsIsReadThroughTheCache(): void
    {
        $provider = new class implements LinkableRouteProviderInterface, LinkableRouteCacheTagsInterface {
            public int $reads = 0;

            public function getLinkableRoutes(): array
            {
                ++$this->reads;

                return ['gallery_category_7' => ['label' => 'Paysages', 'translation_domain' => false, 'route' => 'gallery_category', 'params' => ['slug' => 'paysages']]];
            }

            public function getLinkableRouteCacheTags(): array
            {
                return ['gallery_galleries'];
            }
        };

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('fr');

        $registry = new LinkableRouteRegistry([$provider], $translator, $cache);
        $this->assertSame(['gallery_galleries'], $registry->cacheTags('gallery_category_7'));
        $this->assertTrue(new LinkableRouteRegistry([$provider], $translator, $cache)->has('gallery_category_7'));
        $this->assertSame(1, $provider->reads);

        $cache->invalidateTags(['gallery_galleries']);
        new LinkableRouteRegistry([$provider], $translator, $cache)->has('gallery_category_7');
        $this->assertSame(2, $provider->reads);
    }

    // One declaring none cannot say when its entries change: read live, and nothing to cache a menu item under
    public function testAProviderDeclaringNoTagsHasNone(): void
    {
        $registry = new LinkableRouteRegistry([$this->createProvider(['contact' => ['label' => 'label.contact', 'translation_domain' => 'contact']])], $this->createTranslator(), new TagAwareAdapter(new ArrayAdapter()));

        $this->assertNull($registry->cacheTags('contact'));
    }
}
