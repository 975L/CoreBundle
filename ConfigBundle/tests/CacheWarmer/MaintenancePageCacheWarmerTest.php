<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\CacheWarmer;

use c975L\ConfigBundle\CacheWarmer\MaintenancePageCacheWarmer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MaintenancePageCacheWarmerTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/maintenance-page-cache-warmer-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    // Leaves no trace of the sandbox once the test finishes
    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->projectDir . '/public/*') ?: []);
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    // The bundle's real template, so the page Apache serves can't drift from the one MaintenanceListener renders
    private function createWarmer(): MaintenancePageCacheWarmer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LConfig');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension());

        return new MaintenancePageCacheWarmer($twig, $this->projectDir);
    }

    // The warmer writes the maintenance template to public/maintenance.html, leaving no temp file behind
    public function testWarmUpWritesTheMaintenancePage(): void
    {
        $this->createWarmer()->warmUp(sys_get_temp_dir());

        $path = $this->projectDir . '/public/' . MaintenancePageCacheWarmer::FILENAME;
        $this->assertFileExists($path);
        $this->assertStringContainsString('label.maintenance_message', (string) file_get_contents($path));
        $this->assertSame([$path], glob($this->projectDir . '/public/*'));
    }

    // A static file carries no nonce: Apache sends no CSP, and a frozen one would authorize nothing
    public function testStandalonePageHasNoNonce(): void
    {
        $this->createWarmer()->warmUp(sys_get_temp_dir());

        $this->assertStringNotContainsString('nonce=', (string) file_get_contents($this->projectDir . '/public/' . MaintenancePageCacheWarmer::FILENAME));
    }

    // Optional, as cache:clear --no-warmup must not need Twig to be ready
    public function testIsOptional(): void
    {
        $this->assertTrue($this->createWarmer()->isOptional());
    }

    // An unwritable public/ fails the warmup loudly rather than leaving the previous page in place
    public function testWarmUpThrowsWhenPublicIsReadOnly(): void
    {
        chmod($this->projectDir . '/public', 0555);

        try {
            $this->expectException(\RuntimeException::class);
            $this->createWarmer()->warmUp(sys_get_temp_dir());
        } finally {
            chmod($this->projectDir . '/public', 0777);
        }
    }
}
