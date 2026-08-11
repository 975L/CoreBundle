<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\VideoPosterImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class VideoPosterImporterTest extends TestCase
{
    private const string YOUTUBE_URL = 'https://www.youtube.com/watch?v=lXqKJvMxEdo';

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];
    }

    private function jpegBytes(): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(16, 9));

        return (string) ob_get_clean();
    }

    private function createBlock(string $kind, array $data): Block
    {
        return new Block()->setKind($kind)->setData($data);
    }

    private function createImporter(array $responses): VideoPosterImporter
    {
        return new VideoPosterImporter(new MockHttpClient($responses));
    }

    // A url belonging to no declared platform is never guessed at - same rule as VideoPlatform::resolve()
    public function testFetchReturnsNullForAnUnrecognizedUrl(): void
    {
        $importer = $this->createImporter([]);

        $this->assertNull($importer->fetch('https://example.com/some-video'));
        $this->assertNull($importer->fetch(null));
        $this->assertNull($importer->fetch(''));
    }

    // TikTok is a declared platform, but serves no still at a guessable address - so there is nothing to request
    public function testFetchReturnsNullForAPlatformWithNoPosterAddress(): void
    {
        $importer = $this->createImporter([]);

        $this->assertNull($importer->fetch('https://www.tiktok.com/@someone/video/1234567890'));
    }

    // "maxresdefault" is missing on plenty of videos: a 404 there is an expected outcome, and the next candidate is tried
    public function testFetchFallsBackToTheNextCandidateOnANotFound(): void
    {
        $importer = $this->createImporter([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($this->jpegBytes(), ['http_code' => 200]),
        ]);

        $file = $importer->fetch(self::YOUTUBE_URL);

        $this->assertNotNull($file);
        $this->temporaryFiles[] = $file->getPathname();
        $this->assertSame('image/jpeg', $file->getMimeType());
    }

    public function testFetchReturnsNullWhenNoCandidateAnswers(): void
    {
        $importer = $this->createImporter([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('', ['http_code' => 404]),
        ]);

        $this->assertNull($importer->fetch(self::YOUTUBE_URL));
    }

    // The mime type is read off the bytes, not off the response header - a 200 carrying anything else is not a poster
    public function testFetchRejectsAResponseThatIsNotAnImage(): void
    {
        $importer = $this->createImporter([
            new MockResponse('<html>not an image</html>', ['http_code' => 200]),
            new MockResponse('<html>not an image</html>', ['http_code' => 200]),
        ]);

        $this->assertNull($importer->fetch(self::YOUTUBE_URL));
    }

    // A submission rejected by validation is never flushed, so Vich never moves the downloaded still and nothing else would ever delete it
    public function testTemporaryFileIsRemovedWhenTheRequestEnds(): void
    {
        $importer = $this->createImporter([
            new MockResponse($this->jpegBytes(), ['http_code' => 200]),
        ]);

        $file = $importer->fetch(self::YOUTUBE_URL);
        $this->assertNotNull($file);
        $path = $file->getPathname();
        $this->temporaryFiles[] = $path;
        $this->assertFileExists($path);

        $importer->removeTemporaryFiles();

        $this->assertFileDoesNotExist($path);
    }

    public function testImportIfRequestedIgnoresAnotherKind(): void
    {
        $block = $this->createBlock('video', ['src' => self::YOUTUBE_URL, 'importPoster' => true]);

        $this->createImporter([])->importIfRequested($block);

        $this->assertCount(0, $block->getMedias());
        $this->assertTrue($block->getData()['importPoster'], 'Another kind\'s data must be left untouched');
    }

    public function testImportIfRequestedDoesNothingWhenTheBoxIsNotTicked(): void
    {
        $block = $this->createBlock('video_iframe', ['src' => self::YOUTUBE_URL]);

        $this->createImporter([])->importIfRequested($block);

        $this->assertCount(0, $block->getMedias());
    }

    public function testImportIfRequestedAttachesThePosterAndClearsTheBox(): void
    {
        $block = $this->createBlock('video_iframe', ['src' => self::YOUTUBE_URL, 'importPoster' => true]);
        $importer = $this->createImporter([new MockResponse($this->jpegBytes(), ['http_code' => 200])]);

        $importer->importIfRequested($block);

        $this->assertCount(1, $block->getMedias());
        $media = $block->getMedias()->first();
        $this->assertNotNull($media->getFile());
        $this->temporaryFiles[] = $media->getFile()->getPathname();
        $this->assertSame($block, $media->getBlock(), 'The poster must be attached to the block it belongs to');
        $this->assertArrayNotHasKey('importPoster', $block->getData(), 'The tick is a one-shot action, not a stored preference');
    }

    // Left ticked, every later save would retry a download that already did not answer
    public function testImportIfRequestedClearsTheBoxEvenWhenTheDownloadFails(): void
    {
        $block = $this->createBlock('video_iframe', ['src' => self::YOUTUBE_URL, 'importPoster' => true]);
        $importer = $this->createImporter([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('', ['http_code' => 404]),
        ]);

        $importer->importIfRequested($block);

        $this->assertCount(0, $block->getMedias());
        $this->assertArrayNotHasKey('importPoster', $block->getData());
    }

    // A poster that is being re-imported replaces the one already there: a player has one, and a second row would leave the template to guess which
    public function testImportIfRequestedReplacesAnExistingPoster(): void
    {
        $block = $this->createBlock('video_iframe', ['src' => self::YOUTUBE_URL, 'importPoster' => true]);
        $importer = $this->createImporter([new MockResponse($this->jpegBytes(), ['http_code' => 200])]);
        $importer->importIfRequested($block);
        $first = $block->getMedias()->first();
        $this->temporaryFiles[] = $first->getFile()->getPathname();

        $block->setData(['src' => self::YOUTUBE_URL, 'importPoster' => true]);
        $importer = $this->createImporter([new MockResponse($this->jpegBytes(), ['http_code' => 200])]);
        $importer->importIfRequested($block);

        $this->assertCount(1, $block->getMedias());
        $this->assertSame($first, $block->getMedias()->first());
        $this->temporaryFiles[] = $first->getFile()->getPathname();
    }
}
