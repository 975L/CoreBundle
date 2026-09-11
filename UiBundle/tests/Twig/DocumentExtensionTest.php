<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Controller\MediaController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Twig\DocumentExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AttributeExtension;

class DocumentExtensionTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory, so the thumbnail existence check can be exercised safely with real filesystem reads (same pattern as StylesheetExtensionTest)
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/document-extension-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        mkdir($this->projectDir . '/private', 0777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // The route is asked for its url and nothing else: the stub answers with the path the parameters name
    private function createExtension(): DocumentExtension
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters): string => MediaController::THUMBNAIL_ROUTE === $name ? '/media/' . $parameters['id'] . '/thumbnail' : ''
        );

        // A path package on "/", as the framework's default one answers asset()
        return new DocumentExtension($this->projectDir, $urlGenerator, new Packages(new PathPackage('/', new EmptyVersionStrategy())));
    }

    private function createMedia(string $filename, bool $membersOnly = false): Media
    {
        $media = new Media()->setFilename($filename)->setMembersOnly($membersOnly);
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 7);

        return $media;
    }

    public function testGetThumbnailPathReplacesExtensionNotAppendsIt(): void
    {
        // VichPdfThumbnailListener::toWebpPath() swaps the extension - "document.pdf" becomes "document.webp", never "document.pdf.webp"
        touch($this->projectDir . '/public/document.webp');

        $this->assertSame('document.webp', $this->createExtension()->getThumbnailPath($this->createMedia('document.pdf')));
    }

    public function testGetThumbnailPathReturnsNullWhenNoThumbnailWasGenerated(): void
    {
        // Ghostscript missing on the server, a fixture/placeholder media with no sidecar file, generation not finished - none of these should ever look like a real thumbnail
        $this->assertNull($this->createExtension()->getThumbnailPath($this->createMedia('document.pdf')));
    }

    // A document reserved to members has no thumbnail under public/, and one left over from before the box was ticked must not show its first page either
    public function testGetThumbnailPathReturnsNullForADocumentReservedToMembers(): void
    {
        touch($this->projectDir . '/public/document.webp');

        $this->assertNull($this->createExtension()->getThumbnailPath($this->createMedia('document.pdf', true)));
    }

    public function testAPublicDocumentsThumbnailIsReachedAtItsPublicAddress(): void
    {
        touch($this->projectDir . '/public/document.webp');

        $this->assertSame('/document.webp', $this->createExtension()->getThumbnailUrl($this->createMedia('document.pdf')));
    }

    // The route, never the file: the route is what tells a member (the thumbnail) from anybody else (a lock), the block rendering this being cached for every visitor
    public function testADocumentReservedToMembersIsReachedThroughTheThumbnailRoute(): void
    {
        touch($this->projectDir . '/private/document.webp');

        $this->assertSame('/media/7/thumbnail', $this->createExtension()->getThumbnailUrl($this->createMedia('document.pdf', true)));
    }

    // No thumbnail on disk, no route either: the block shows its plain placeholder rather than a lock on a document a member could open
    public function testADocumentReservedToMembersWithoutAThumbnailHasNoUrl(): void
    {
        $this->assertNull($this->createExtension()->getThumbnailUrl($this->createMedia('document.pdf', true)));
        $this->assertNull($this->createExtension()->getThumbnailUrl($this->createMedia('document.pdf')));
    }

    public function testGetFunctionsRegistersBothDocumentThumbnailFunctions(): void
    {
        $names = array_map(fn ($f) => $f->getName(), new AttributeExtension(DocumentExtension::class)->getFunctions());

        $this->assertSame(['document_thumbnail_path', 'document_thumbnail_url'], $names);
    }
}
