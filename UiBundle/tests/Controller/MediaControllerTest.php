<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\MediaController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\PrivateFileResponseFactory;
use c975L\UiBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MediaControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-controller-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/private/medias/site');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function createController(bool $granted): MediaController
    {
        $controller = new MediaController(new PrivateFileResponseFactory(), $this->projectDir);
        $controller->setContainer($this->createContainer(['security.authorization_checker' => $this->createAuthorizationChecker($granted)]));

        return $controller;
    }

    private function createMedia(bool $membersOnly): Media
    {
        return new Media()->setFilename('medias/site/tree.pdf')->setMembersOnly($membersOnly);
    }

    public function testASignedInVisitorOpensTheDocumentInPlace(): void
    {
        file_put_contents($this->projectDir . '/private/medias/site/tree.pdf', '%PDF-1.4');

        $response = $this->createController(true)->open($this->createMedia(true));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    // The firewall turns this into the login form for an anonymous visitor, and brings them back once signed in
    public function testAVisitorWhoIsNotSignedInIsRefused(): void
    {
        file_put_contents($this->projectDir . '/private/medias/site/tree.pdf', '%PDF-1.4');

        $this->expectException(AccessDeniedException::class);

        $this->createController(false)->open($this->createMedia(true));
    }

    // A public media keeps the one address the web server gives it
    public function testAPublicMediaHasNoAddressHere(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(true)->open($this->createMedia(false));
    }

    public function testAFileMissingFromDiskIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(true)->open($this->createMedia(true));
    }

    // The thumbnail kept next to the document, to who may open it
    public function testASignedInVisitorSeesTheThumbnail(): void
    {
        file_put_contents($this->projectDir . '/private/medias/site/tree.webp', 'webp');

        $response = $this->createController(true)->thumbnail($this->createMedia(true));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame($this->projectDir . '/private/medias/site/tree.webp', $response->getFile()->getPathname());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    // Anybody else gets the lock rather than a refusal - an <img> sent to the login form shows a broken picture - and the lock is never kept, the same url answering with the thumbnail once signed in
    public function testAVisitorWhoIsNotSignedInSeesALock(): void
    {
        file_put_contents($this->projectDir . '/private/medias/site/tree.webp', 'webp');

        $response = $this->createController(false)->thumbnail($this->createMedia(true));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringEndsWith('document-locked.svg', $response->getFile()->getPathname());
        $this->assertFileExists($response->getFile()->getPathname());
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertNull($response->getLastModified());
    }

    // A public document's thumbnail keeps its public address
    public function testAPublicMediaHasNoThumbnailHere(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(true)->thumbnail($this->createMedia(false));
    }

    public function testAThumbnailMissingFromDiskIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(true)->thumbnail($this->createMedia(true));
    }
}
