<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\VideoExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoExtensionTest extends TestCase
{
    #[DataProvider('provideYoutubeUrls')]
    public function testToPrivacyEmbedUrlRewritesYoutubeHosts(string $url, string $expected): void
    {
        $extension = new VideoExtension();

        $this->assertSame($expected, $extension->toPrivacyEmbedUrl($url));
    }

    public static function provideYoutubeUrls(): iterable
    {
        yield 'youtube.com' => [
            'https://youtube.com/embed/abc123',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        yield 'www.youtube.com' => [
            'https://www.youtube.com/embed/abc123',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        yield 'm.youtube.com' => [
            'https://m.youtube.com/embed/abc123',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        yield 'youtu.be' => [
            'https://youtu.be/abc123',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        yield 'youtube.com watch URL' => [
            'https://www.youtube.com/watch?v=abc123',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        yield 'youtube.com watch URL with extra query params' => [
            'https://www.youtube.com/watch?v=abc123&t=42s',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
    }

    #[DataProvider('provideUnaffectedUrls')]
    public function testToPrivacyEmbedUrlLeavesOtherUrlsUntouched(?string $url): void
    {
        $extension = new VideoExtension();

        $this->assertSame($url, $extension->toPrivacyEmbedUrl($url));
    }

    public static function provideUnaffectedUrls(): iterable
    {
        yield 'vimeo' => ['https://player.vimeo.com/video/123456'];
        yield 'dailymotion' => ['https://www.dailymotion.com/embed/video/x123'];
        yield 'already nocookie' => ['https://www.youtube-nocookie.com/embed/abc123'];
        // Left as-is even without "/embed/": whoever built this URL already made the privacy-safe choice themselves, not second-guessed by adding a path they may have deliberately omitted
        yield 'already nocookie bare path' => ['https://www.youtube-nocookie.com/abc123'];
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'not a URL' => ['not-a-url'];
        yield 'youtube.com watch URL without a v param' => ['https://www.youtube.com/watch?list=abc123'];
    }

    public function testGetFiltersRegistersPrivacyEmbedUrlFilter(): void
    {
        $extension = new VideoExtension();
        $filters = $extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('privacy_embed_url', $filters[0]->getName());
    }
}
