<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbstractDeclaredFilesHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/declared-files-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<int, array{filename: string, label: string, editUrl: ?string, directory?: string}> $files
     */
    private function createProvider(array $files): DeclaredFilesHealthCheckProviderTestSubject
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new DeclaredFilesHealthCheckProviderTestSubject($files, $configService, $translator, $this->projectDir);
    }

    private function writeFile(string $directory, string $filename): void
    {
        $path = $this->projectDir . '/' . $directory . '/' . $filename;
        new Filesystem()->mkdir(\dirname($path));
        file_put_contents($path, 'file');
    }

    /**
     * @return array{filename: string, label: string, editUrl: ?string, directory?: string}
     */
    private function declare(string $filename, ?string $directory = null): array
    {
        $file = ['filename' => $filename, 'label' => 'Une image', 'editUrl' => null];

        return null === $directory ? $file : $file + ['directory' => $directory];
    }

    // A row naming no directory hangs off public/, which is where every uploaded file lands unless something moves it
    public function testAFileIsLookedForUnderPublicByDefault(): void
    {
        $this->writeFile('public', 'medias/site/logo.webp');

        $rows = $this->createProvider([$this->declare('medias/site/logo.webp')])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    public function testAFileMissingFromPublicIsAnError(): void
    {
        $rows = $this->createProvider([$this->declare('medias/site/logo.webp')])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
    }

    // What ShopBundle's digital items need: the file is served by a controller from outside public/, and looking for it there would report every one of them missing
    public function testARowNamingItsOwnDirectoryIsLookedForThere(): void
    {
        $this->writeFile('private', 'medias/shop/items/triados.pdf');

        $rows = $this->createProvider([$this->declare('medias/shop/items/triados.pdf', 'private')])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    public function testARowNamingItsOwnDirectoryIgnoresACopyLeftUnderPublic(): void
    {
        $this->writeFile('public', 'medias/shop/items/triados.pdf');

        $rows = $this->createProvider([$this->declare('medias/shop/items/triados.pdf', 'private')])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
    }

    // The row's identity stays the public url whatever directory holds the file: it is what an exhaustive purge retires a row by
    public function testAPrivateFileKeepsThePublicUrlAsItsRowIdentity(): void
    {
        $this->writeFile('private', 'medias/shop/items/triados.pdf');

        $rows = $this->createProvider([$this->declare('medias/shop/items/triados.pdf', 'private')])->runChecks();

        $this->assertSame('https://example.com/medias/shop/items/triados.pdf', $rows[0]['url']);
    }

    public function testARowNamingNoFileIsSkipped(): void
    {
        $this->assertSame([], $this->createProvider([$this->declare('')])->runChecks());
    }
}
