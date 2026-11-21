<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\SvgSanitizeListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class SvgSanitizeListenerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/svg-sanitize-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->projectDir . '/public/*'));
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    // Runs ahead of the other post_upload listeners, which then all read the cleaned file
    public function testRunsBeforeTheOtherUploadListeners(): void
    {
        $listener = new \ReflectionClass(SvgSanitizeListener::class)->getAttributes(AsEventListener::class)[0]->newInstance();

        $this->assertSame(['vich_uploader.post_upload', 20], [$listener->event, $listener->priority]);
    }

    // Scripts, event handlers and remote references are stripped, the drawing kept
    public function testAnUploadedSvgLosesItsScriptsAndHandlers(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)" viewBox="0 0 10 10">'
            . '<script>alert(document.cookie)</script>'
            . '<rect width="10" height="10" fill="#000" onclick="alert(2)"/>'
            . '<use xlink:href="https://evil.example/item-1.svg#a"/>'
            . '</svg>';

        $this->upload('item-1.svg', $svg);
        $stored = (string) file_get_contents($this->projectDir . '/public/item-1.svg');

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('evil.example', $stored);
        $this->assertStringContainsString('<rect', $stored);
    }

    // An icon role's file carries the role's extension while still holding the uploaded markup: its bytes decide
    public function testAnSvgStoredUnderAnotherNameIsSanitizedToo(): void
    {
        $this->upload('favicon.ico', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>');

        $this->assertStringNotContainsString('onload', (string) file_get_contents($this->projectDir . '/public/favicon.ico'));
    }

    // A file named svg that does not parse is removed and refused
    public function testAnSvgThatCannotBeParsedIsRefused(): void
    {
        try {
            $this->upload('item-1.svg', '<svg><script>alert(1)</scr');
            $this->fail('An unparsable SVG was stored.');
        } catch (\RuntimeException) {
            $this->assertFileDoesNotExist($this->projectDir . '/public/item-1.svg');
        }
    }

    // Anything else is left exactly as uploaded
    public function testAnythingElseIsLeftAlone(): void
    {
        $this->upload('item-1.txt', '<script>alert(1)</script>');

        $this->assertSame('<script>alert(1)</script>', file_get_contents($this->projectDir . '/public/item-1.txt'));
    }

    private function upload(string $name, string $contents): void
    {
        $path = $this->projectDir . '/public/' . $name;
        file_put_contents($path, $contents);

        $media = new Media();
        $media->setFilename($name);
        $media->setFile(new File($path));

        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->projectDir . '/public', 'uri_prefix' => '']);

        new SvgSanitizeListener($this->projectDir)->onPostUpload(new Event($media, $mapping));
    }
}
