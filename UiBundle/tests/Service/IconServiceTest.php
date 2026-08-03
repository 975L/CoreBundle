<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\IconService;
use PHPUnit\Framework\TestCase;

class IconServiceTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory, so real filesystem globbing can be exercised safely
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/icon-service-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    // Leaves no trace of the sandbox project directory once the test finishes
    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // Creates an empty SVG icon file at the given path relative to the sandbox public directory
    private function createIcon(string $relativePathFromPublic): void
    {
        $path = $this->projectDir . '/public/' . $relativePathFromPublic;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, '<svg></svg>');
    }

    // Builds the service under test, wired to the sandbox project directory instead of a real kernel
    private function createService(): IconService
    {
        return new IconService($this->projectDir);
    }

    // Recursively deletes the sandbox directory tree created for a test
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testGetIconsReturnsEmptyArrayWhenNoIconDirectoriesExist(): void
    {
        $service = $this->createService();

        $this->assertSame([], $service->getIcons());
    }

    public function testGetIconsFindsIconsAtProjectRoot(): void
    {
        $this->createIcon('icons/home.svg');
        $service = $this->createService();

        $this->assertSame(['home' => 'icons/home.svg'], $service->getIcons());
    }

    public function testGetIconsFindsIconsContributedByBundles(): void
    {
        $this->createIcon('bundles/c975lsitebundle/icons/star.svg');
        $service = $this->createService();

        $this->assertSame(
            ['star' => 'bundles/c975lsitebundle/icons/star.svg'],
            $service->getIcons()
        );
    }

    // The icons array must be sorted by name, regardless of the order in which files were discovered on disk
    public function testGetIconsSortsResultsAlphabeticallyByName(): void
    {
        $this->createIcon('icons/zebra.svg');
        $this->createIcon('icons/apple.svg');
        $this->createIcon('bundles/somebundle/icons/mango.svg');
        $service = $this->createService();

        $this->assertSame(['apple', 'mango', 'zebra'], array_keys($service->getIcons()));
    }

    // When a bundle and the project root both define an icon under the same name, the project-level icon must win, since it is scanned after the bundle icons and overwrites the array entry
    public function testGetIconsLetsProjectRootIconOverrideBundleIconOfSameName(): void
    {
        $this->createIcon('bundles/somebundle/icons/shared.svg');
        $this->createIcon('icons/shared.svg');
        $service = $this->createService();

        $this->assertSame(['shared' => 'icons/shared.svg'], $service->getIcons());
    }
}
