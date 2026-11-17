<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\NotFoundFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotFoundFilterTest extends TestCase
{
    // Links someone published and that no longer answer, images included
    #[DataProvider('brokenLinkProvider')]
    public function testKeepsABrokenLink(string $path, string $referer): void
    {
        $this->assertTrue(new NotFoundFilter()->isWorthRecording($path, $referer));
    }

    // One per kind of link worth a redirect
    public static function brokenLinkProvider(): iterable
    {
        yield 'internal page' => ['/catalog/item-1', 'https://example.com/catalog'];
        yield 'external page' => ['/demo', 'https://elsewhere.example/readme'];
        yield 'hotlinked image' => ['/images/logo.png', 'https://elsewhere.example/catalog/item-1'];
    }

    // The noise a forged referer still lets through: the shape of the path or of the referer is what gives it away
    #[DataProvider('noiseProvider')]
    public function testTurnsDownTheNoise(string $path, string $referer): void
    {
        $this->assertFalse(new NotFoundFilter()->isWorthRecording($path, $referer));
    }

    // One per family the filter covers
    public static function noiseProvider(): iterable
    {
        yield 'no referer' => ['/catalog/item-1', ''];
        yield 'self referer' => ['/console', 'http://example.com/console'];
        yield 'self referer with doubled and trailing slashes' => ['/upload/server/php/', 'https://example.com//upload/server/php'];
        yield 'self referer in another case' => ['/plugins/jQuery-File-Upload/', 'https://example.com//plugins/jquery-file-upload'];
        yield 'sitemap guess' => ['/sitemap.xml.gz', 'https://example.com'];
        yield 'data file' => ['/secrets.json', 'https://google.com/search?q=example.com'];
        yield 'text file' => ['/plugins/readme.txt', 'https://example.com/'];
        yield 'script outside assets' => ['/npm/library@1.0.0/dist/library.js', 'https://example.com/'];
        yield 'stylesheet' => ['/theme/site.css', 'https://example.com/'];
        yield 'graphql' => ['/graphql', 'https://www.example.com/'];
        yield 'referer that is a robots.txt' => ['/catalog', 'https://example.com/robots.txt'];
        yield 'wordpress' => ['/wp-admin/css/', 'https://www.binance.com/'];
        yield 'not a web url' => ['/catalog', 'javascript://example.com/%0aalert(1)'];
    }

    // The host alone decides, whatever the case it is written in
    public function testTellsAnInternalRefererByItsHost(): void
    {
        $filter = new NotFoundFilter();

        $this->assertTrue($filter->isInternal('https://Example.com/catalog', 'example.com'));
        $this->assertFalse($filter->isInternal('https://elsewhere.example/catalog', 'example.com'));
    }
}
