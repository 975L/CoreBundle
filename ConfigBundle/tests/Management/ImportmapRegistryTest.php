<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\ConfigImportmapProvider;
use c975L\ConfigBundle\Management\ImportmapProviderInterface;
use c975L\ConfigBundle\Management\ImportmapRegistry;
use c975L\ConfigBundle\Service\BundleLocator;
use PHPUnit\Framework\TestCase;

class ImportmapRegistryTest extends TestCase
{
    private function createProvider(array $adminEntries, array $entries = []): ImportmapProviderInterface
    {
        $provider = $this->createStub(ImportmapProviderInterface::class);
        $provider->method('getAdminImportmapEntries')->willReturn($adminEntries);
        $provider->method('getImportmapEntries')->willReturn($entries);

        return $provider;
    }

    public function testAllReturnsEveryAdminEntryMergedAcrossProviders(): void
    {
        $providerA = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);
        $providerB = $this->createProvider([
            '@c975l/ui-bundle/controllers.js' => ['path' => './vendor/c975l/ui-bundle/assets/controllers.js', 'entrypoint' => true],
        ]);
        $registry = new ImportmapRegistry([$providerA, $providerB], new BundleLocator([]), '/app');

        $this->assertSame([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
            '@c975l/ui-bundle/controllers.js' => ['path' => './vendor/c975l/ui-bundle/assets/controllers.js', 'entrypoint' => true],
        ], $registry->all());
    }

    public function testAllMergesAdminAndNonAdminEntriesFromTheSameProvider(): void
    {
        $provider = $this->createProvider(
            ['@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true]],
            ['@c975l/config-bundle/controllers.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers.js', 'entrypoint' => true]],
        );
        $registry = new ImportmapRegistry([$provider], new BundleLocator([]), '/app');

        $this->assertSame([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
            '@c975l/config-bundle/controllers.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers.js', 'entrypoint' => true],
        ], $registry->all());
    }

    public function testAllReturnsEmptyArrayWhenNoProvider(): void
    {
        $registry = new ImportmapRegistry([], new BundleLocator([]), '/app');

        $this->assertSame([], $registry->all());
    }

    // The very case the merge introduced: the bundle declares 'assets/…' and the registry writes where that bundle really sits, one directory below its package
    public function testAPathIsPrefixedWithTheDeclaringBundlesOwnDirectory(): void
    {
        $registry = new ImportmapRegistry(
            [new ConfigImportmapProvider()],
            new BundleLocator(['c975LConfigBundle' => ['path' => '/app/vendor/c975l/core-bundle/ConfigBundle', 'namespace' => 'c975L\ConfigBundle']]),
            '/app',
        );

        $this->assertSame(
            './vendor/c975l/core-bundle/ConfigBundle/assets/controllers-admin.js',
            $registry->all()['@c975l/config-bundle/controllers-admin.js']['path']
        );
    }

    // A provider the application ships itself belongs to no bundle: its path is already the project root's own and must be left alone
    public function testAPathDeclaredOutsideAnyC975LBundleIsLeftUntouched(): void
    {
        $provider = $this->createProvider(['@app/controllers.js' => ['path' => './assets/controllers.js', 'entrypoint' => true]]);
        $registry = new ImportmapRegistry([$provider], new BundleLocator([]), '/app');

        $this->assertSame('./assets/controllers.js', $registry->all()['@app/controllers.js']['path']);
    }
}
