<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;
use c975L\UiBundle\Registry\StylesheetRegistry;
use PHPUnit\Framework\TestCase;

class StylesheetRegistryTest extends TestCase
{
    private function createProvider(array $stylesheets): BundleStylesheetProviderInterface
    {
        $provider = $this->createStub(BundleStylesheetProviderInterface::class);
        $provider->method('getStylesheets')->willReturn($stylesheets);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new StylesheetRegistry();

        $this->assertSame([], $registry->all());
    }

    public function testAllMergesStylesheetsFromEveryProvider(): void
    {
        $registry = new StylesheetRegistry();
        $registry->addProvider($this->createProvider(['a.css']));
        $registry->addProvider($this->createProvider(['b.css']));

        $this->assertSame(['a.css', 'b.css'], $registry->all());
    }

    // A stylesheet contributed by two different providers must appear only once, in declaration order
    public function testAllDeduplicatesStylesheetsAcrossProviders(): void
    {
        $registry = new StylesheetRegistry();
        $registry->addProvider($this->createProvider(['a.css', 'b.css']));
        $registry->addProvider($this->createProvider(['b.css', 'c.css']));

        $this->assertSame(['a.css', 'b.css', 'c.css'], $registry->all());
    }

    public function testIsExternalIsTrueForAnHttpUrl(): void
    {
        $this->assertTrue(StylesheetRegistry::isExternal('https://cdn.example.com/lib.css'));
    }

    public function testIsExternalIsFalseForALocalPath(): void
    {
        $this->assertFalse(StylesheetRegistry::isExternal('bundles/c975lui/css/styles.min.css'));
    }

    // An app asset is read from the project root, a bundle's compiled sheet from public/, so both callers (the cache warmer and the Twig extension) have to agree on which of the two a path names
    public function testIsAppAssetIsTrueForAPathUnderAssets(): void
    {
        $this->assertTrue(StylesheetRegistry::isAppAsset('assets/styles/themes/ui.css'));
    }

    public function testIsAppAssetIsFalseForABundlesCompiledStylesheet(): void
    {
        $this->assertFalse(StylesheetRegistry::isAppAsset('bundles/c975lui/css/styles.min.css'));
    }

    public function testIsAppAssetIsFalseForAnExternalUrl(): void
    {
        $this->assertFalse(StylesheetRegistry::isAppAsset('https://cdn.example.com/lib.css'));
    }

    // A generated sheet goes through no asset manifest, so it is the one kind the Twig extension has to version by mtime rather than by hash
    public function testIsGeneratedIsTrueForAPathUnderBundlesBuild(): void
    {
        $this->assertTrue(StylesheetRegistry::isGenerated('bundles/build/site-theme.css'));
    }

    public function testIsGeneratedIsFalseForABundlesShippedStylesheet(): void
    {
        $this->assertFalse(StylesheetRegistry::isGenerated('bundles/c975lui/css/styles.min.css'));
    }

    public function testIsGeneratedIsFalseForAnAppAsset(): void
    {
        $this->assertFalse(StylesheetRegistry::isGenerated('assets/styles/themes/ui.css'));
    }

    // AssetMapper's root being the assets/ directory itself, the prefix is not part of the logical path
    public function testLogicalPathDropsTheAssetsPrefix(): void
    {
        $this->assertSame('styles/themes/ui.css', StylesheetRegistry::logicalPath('assets/styles/themes/ui.css'));
    }

    public function testLogicalPathLeavesAnyOtherPathUntouched(): void
    {
        $this->assertSame(
            'bundles/c975lui/css/styles.min.css',
            StylesheetRegistry::logicalPath('bundles/c975lui/css/styles.min.css')
        );
    }

    // "assets" only counts as the prefix when it is a directory of its own, not the head of a longer name
    public function testLogicalPathLeavesAPathMerelyStartingWithTheWordAssetsUntouched(): void
    {
        $this->assertSame('assetsmap/theme.css', StylesheetRegistry::logicalPath('assetsmap/theme.css'));
    }
}
