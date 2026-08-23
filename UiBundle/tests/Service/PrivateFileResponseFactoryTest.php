<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\PrivateFileResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PrivateFileResponseFactoryTest extends TestCase
{
    private string $sourceDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/private-file-response-factory-test-' . uniqid();
        mkdir($this->sourceDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sourceDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->sourceDir);
    }

    public function testCreateDownloadResponseReturnsNullWhenFileIsMissing(): void
    {
        $factory = new PrivateFileResponseFactory();

        $this->assertNull($factory->createDownloadResponse($this->sourceDir . '/missing.pdf', 'report.pdf'));
    }

    public function testCreateDownloadResponseBuildsAnAttachmentResponseWithTheDownloadFilename(): void
    {
        $absolutePath = $this->sourceDir . '/token-abc123.pdf';
        file_put_contents($absolutePath, 'content');

        $factory = new PrivateFileResponseFactory();
        $response = $factory->createDownloadResponse($absolutePath, 'invoice.pdf');

        $this->assertNotNull($response);
        $this->assertSame($absolutePath, $response->getFile()->getPathname());
        $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('filename=invoice.pdf', $response->headers->get('Content-Disposition'));
    }

    public function testCreateInlineResponseReturnsNullWhenFileIsMissing(): void
    {
        $factory = new PrivateFileResponseFactory();

        $this->assertNull($factory->createInlineResponse($this->sourceDir . '/missing.mp4'));
    }

    public function testCreateInlineResponseBuildsAnInlineResponseTheBrowserPlaysInThePage(): void
    {
        $absolutePath = $this->sourceDir . '/asset-abc123.mp4';
        file_put_contents($absolutePath, 'content');

        $factory = new PrivateFileResponseFactory();
        $response = $factory->createInlineResponse($absolutePath, 'teaser.mp4');

        $this->assertNotNull($response);
        $this->assertSame($absolutePath, $response->getFile()->getPathname());
        $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_INLINE, $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('filename=teaser.mp4', $response->headers->get('Content-Disposition'));
    }

    // A paid asset kept by a shared cache would be served to whoever asks that cache next
    public function testCreateInlineResponseIsPrivateAndNosniff(): void
    {
        $absolutePath = $this->sourceDir . '/asset-abc123.jpg';
        file_put_contents($absolutePath, 'content');

        $factory = new PrivateFileResponseFactory();
        $response = $factory->createInlineResponse($absolutePath);

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    // The file's own name when none is given, so a caller gating a media never has to repeat it
    public function testCreateInlineResponseFallsBackToTheFilesOwnName(): void
    {
        $absolutePath = $this->sourceDir . '/asset-abc123.jpg';
        file_put_contents($absolutePath, 'content');

        $factory = new PrivateFileResponseFactory();
        $response = $factory->createInlineResponse($absolutePath);

        $this->assertStringContainsString('filename=asset-abc123.jpg', $response->headers->get('Content-Disposition'));
    }

    // Regression: rebuilding the filename via SplFileInfo::getBasename()/getExtension() used to append a stray trailing dot when $downloadFilename had no extension
    public function testCreateDownloadResponseDoesNotAppendATrailingDotForAnExtensionLessFilename(): void
    {
        $absolutePath = $this->sourceDir . '/token-abc123';
        file_put_contents($absolutePath, 'content');

        $factory = new PrivateFileResponseFactory();
        $response = $factory->createDownloadResponse($absolutePath, 'report');

        $this->assertSame('attachment; filename=report', $response->headers->get('Content-Disposition'));
    }
}
