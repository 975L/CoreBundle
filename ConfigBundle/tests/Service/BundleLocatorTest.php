<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\BundleLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class BundleLocatorTest extends TestCase
{
    private string $rootDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->rootDir = sys_get_temp_dir() . '/c975l-bundle-locator-' . uniqid();
        $this->filesystem->mkdir($this->rootDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->rootDir);
    }

    public function testDirectoriesKeepsTheC975LBundlesOnly(): void
    {
        $locator = new BundleLocator([
            'FrameworkBundle' => ['path' => '/vendor/symfony/framework-bundle', 'namespace' => 'Symfony\Bundle\FrameworkBundle'],
            'c975LConfigBundle' => ['path' => '/vendor/c975l/core-bundle/ConfigBundle', 'namespace' => 'c975L\ConfigBundle'],
            'c975LUiBundle' => ['path' => '/vendor/c975l/core-bundle/UiBundle', 'namespace' => 'c975L\UiBundle'],
        ]);

        $this->assertSame([
            'c975LConfigBundle' => '/vendor/c975l/core-bundle/ConfigBundle',
            'c975LUiBundle' => '/vendor/c975l/core-bundle/UiBundle',
        ], $locator->directories());
    }

    // The very layout the merge introduced: two bundles inside one package, one directory below what a "vendor/c975l/<package>" glob ever looked at
    public function testDirectoriesFollowsTwoBundlesShippedInOnePackage(): void
    {
        $package = $this->rootDir . '/vendor/c975l/core-bundle';
        $this->filesystem->mkdir([$package . '/ConfigBundle/config', $package . '/UiBundle/config']);

        $locator = new BundleLocator([
            'c975LConfigBundle' => ['path' => $package . '/ConfigBundle', 'namespace' => 'c975L\ConfigBundle'],
            'c975LUiBundle' => ['path' => $package . '/UiBundle', 'namespace' => 'c975L\UiBundle'],
        ]);

        $this->assertSame([
            'c975LConfigBundle' => $package . '/ConfigBundle/config',
            'c975LUiBundle' => $package . '/UiBundle/config',
        ], $locator->subdirectories('config'));
    }

    public function testSubdirectoriesSkipsABundleNotShippingOne(): void
    {
        $this->filesystem->mkdir($this->rootDir . '/with/scaffold');
        $this->filesystem->mkdir($this->rootDir . '/without');

        $locator = new BundleLocator([
            'c975LWithBundle' => ['path' => $this->rootDir . '/with', 'namespace' => 'c975L\WithBundle'],
            'c975LWithoutBundle' => ['path' => $this->rootDir . '/without', 'namespace' => 'c975L\WithoutBundle'],
        ]);

        $this->assertSame(['c975LWithBundle' => $this->rootDir . '/with/scaffold'], $locator->subdirectories('scaffold'));
    }

    public function testAnApplicationRunningNoC975LBundleGetsNothing(): void
    {
        $locator = new BundleLocator(['TwigBundle' => ['path' => '/vendor/symfony/twig-bundle', 'namespace' => 'Symfony\Bundle\TwigBundle']]);

        $this->assertSame([], $locator->directories());
        $this->assertSame([], $locator->subdirectories('config'));
    }
}
