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
use c975L\UiBundle\Listener\SvgTextWarningListener;
use c975L\UiBundle\Service\SvgTextDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class SvgTextWarningListenerTest extends TestCase
{
    private string $projectDir;

    private Session $session;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/svg-text-warning-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        $this->session = new Session(new MockArraySessionStorage());
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->projectDir . '/public/*'));
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    private function createMapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->projectDir . '/public', 'uri_prefix' => '']);

        return $mapping;
    }

    private function write(string $name, string $contents): Media
    {
        $path = $this->projectDir . '/public/' . $name;
        file_put_contents($path, $contents);

        $media = new Media();
        $media->setFilename($name);
        $media->setFile(new File($path));

        return $media;
    }

    private function createListener(bool $withSession = true): SvgTextWarningListener
    {
        $request = new Request();
        if ($withSession) {
            $request->setSession($this->session);
        }
        $requestStack = new RequestStack([$request]);

        $translator = $this->createStub(TranslatorInterface::class);
        // Echoes the parameters back, so the assertions can read what the message would have carried
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new SvgTextWarningListener(new SvgTextDetector(), $requestStack, $translator, $this->projectDir);
    }

    private function flashes(): array
    {
        return $this->session->getFlashBag()->get('warning');
    }

    public function testAnSvgDrawingTextIsFlagged(): void
    {
        $media = $this->write('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><text font-family="Riffic Free">975L</text></svg>');

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        $flashes = $this->flashes();
        $this->assertCount(1, $flashes);
        $this->assertStringContainsString('text.svg_text_not_vectorized', $flashes[0]);
        $this->assertStringContainsString('logo.svg', $flashes[0]);
        $this->assertStringContainsString('(Riffic Free)', $flashes[0]);
    }

    public function testAVectorizedSvgIsNotFlagged(): void
    {
        $media = $this->write('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0 L1 1 Z"/></svg>');

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame([], $this->flashes());
    }

    public function testARasterUploadIsNotFlagged(): void
    {
        $media = $this->write('photo.png', "\x89PNG\r\n\x1a\n");

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame([], $this->flashes());
    }

    // A content_import or a console command has nobody to warn, and asking for the flash bag there would throw
    public function testNothingIsFlashedWithoutASession(): void
    {
        $media = $this->write('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><text>975L</text></svg>');

        $this->createListener(false)->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame([], $this->flashes());
    }
}
