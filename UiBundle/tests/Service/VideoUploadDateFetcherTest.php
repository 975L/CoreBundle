<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\VideoUploadDateFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class VideoUploadDateFetcherTest extends TestCase
{
    // The watch page's own structured data, the consent cookie sent so a European server is not served the consent wall
    public function testYoutubeIsReadOffItsWatchPage(): void
    {
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requested): MockResponse {
            $requested = [$url, implode("\n", $options['headers'] ?? [])];

            return new MockResponse('<meta itemprop="uploadDate" content="2009-10-24T23:57:33-07:00">');
        });

        $this->assertSame('2009-10-24', new VideoUploadDateFetcher($client)->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $requested[0]);
        $this->assertStringContainsString('CONSENT=YES', $requested[1]);
    }

    public function testVimeoIsReadOffItsOembed(): void
    {
        $client = new MockHttpClient(new MockResponse('{"upload_date":"2011-04-15 08:35:35"}'));

        $this->assertSame('2011-04-15', new VideoUploadDateFetcher($client)->fetch('https://vimeo.com/22439234'));
    }

    public function testDailymotionIsReadOffItsApi(): void
    {
        $client = new MockHttpClient(new MockResponse('{"created_time":1678804685}'));

        $this->assertSame('2023-03-14', new VideoUploadDateFetcher($client)->fetch('https://www.dailymotion.com/video/x8j3d1c'));
    }

    // A platform that did not answer leaves the block undated rather than failing the whole run
    public function testAPlatformThatDoesNotAnswerGivesNoDate(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 404]));

        $this->assertNull(new VideoUploadDateFetcher($client)->fetch('https://vimeo.com/76979871'));
    }

    public function testAUrlOfNoPlatformAsksNothing(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            $this->fail('No request should be made for a url of no platform.');
        });

        $this->assertNull(new VideoUploadDateFetcher($client)->fetch('https://example.test/film.mp4'));
    }
}
