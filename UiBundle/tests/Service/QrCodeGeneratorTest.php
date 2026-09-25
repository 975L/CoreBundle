<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Model\QrCodeOptions;
use c975L\UiBundle\Service\QrCodeGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;

class QrCodeGeneratorTest extends TestCase
{
    public function testItDrawsAPngByDefault(): void
    {
        $image = new QrCodeGenerator(new TagAwareAdapter(new ArrayAdapter()))->generate('https://example.com/page');

        $this->assertSame('image/png', $image->mimeType);
        $this->assertStringStartsWith("\x89PNG", $image->content);
        $this->assertStringStartsWith('data:image/png;base64,', $image->getDataUri());
    }

    public function testItDrawsAnSvgWhenAsked(): void
    {
        $image = new QrCodeGenerator(new TagAwareAdapter(new ArrayAdapter()))->generate('https://example.com/page', new QrCodeOptions(format: QrCodeOptions::FORMAT_SVG, label: 'ignored'));

        $this->assertSame('image/svg+xml', $image->mimeType);
        $this->assertStringContainsString('<svg', $image->content);
    }

    // The whole point of the service: a code asked for again is read back, not drawn a second time
    public function testASecondCallIsReadFromTheCache(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $generator = new QrCodeGenerator($cache);
        $first = $generator->generate('https://example.com/page');

        $this->assertTrue($cache->hasItem('ui_qrcode_' . hash('xxh128', serialize(['https://example.com/page', new QrCodeOptions()]))));
        $this->assertSame($first->content, $generator->generate('https://example.com/page')->content);
    }

    // Two drawings of the same data are two entries, or a label-less code would be served where a labelled one was asked for
    public function testOptionsArePartOfTheKey(): void
    {
        $generator = new QrCodeGenerator(new TagAwareAdapter(new ArrayAdapter()));

        $this->assertNotSame(
            $generator->generate('https://example.com/page')->content,
            $generator->generate('https://example.com/page', new QrCodeOptions(size: 400))->content,
        );
    }

    // What lets a shortcut or a page drop its codes when the url they encode changes
    public function testInvalidatingAnOwnerTagDropsItsCodes(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $generator = new QrCodeGenerator($cache);
        $generator->generate('https://example.com/a', tags: ['owner_a']);
        $generator->generate('https://example.com/b', tags: ['owner_b']);

        $cache->invalidateTags(['owner_a']);

        $this->assertFalse($cache->hasItem('ui_qrcode_' . hash('xxh128', serialize(['https://example.com/a', new QrCodeOptions()]))));
        $this->assertTrue($cache->hasItem('ui_qrcode_' . hash('xxh128', serialize(['https://example.com/b', new QrCodeOptions()]))));
    }

    public function testTheResponseAnswersNotModifiedToAMatchingEtag(): void
    {
        $generator = new QrCodeGenerator(new TagAwareAdapter(new ArrayAdapter()));
        $image = $generator->generate('https://example.com/page');
        $etag = $generator->response($image, new Request())->getEtag();

        $request = new Request();
        $request->headers->set('If-None-Match', (string) $etag);
        $response = $generator->response($image, $request, true);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }
}
