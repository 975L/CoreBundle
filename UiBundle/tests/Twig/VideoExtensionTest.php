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

// The url forms themselves are covered by Tests\Video\VideoPlatformTest - what is checked here is only what the filter adds on top of the registry: rewriting what resolves, leaving alone what doesn't
class VideoExtensionTest extends TestCase
{
    #[DataProvider('provideRewrittenUrls')]
    public function testToPrivacyEmbedUrlRewritesToTheCanonicalEmbedUrl(string $url, string $expected): void
    {
        $extension = new VideoExtension();

        $this->assertSame($expected, $extension->toPrivacyEmbedUrl($url));
    }

    public static function provideRewrittenUrls(): iterable
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
        // Player parameters do not survive: only the id is read back, the same as this filter always did with a "/watch" url's own query string
        yield 'youtube.com watch URL with extra query params' => [
            'https://www.youtube.com/watch?v=abc123&t=42s',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        yield 'already nocookie' => [
            'https://www.youtube-nocookie.com/embed/abc123',
            'https://www.youtube-nocookie.com/embed/abc123',
        ];
        // Platforms the filter used to leave untouched, now carried by the registry - Vimeo gains its own do-not-track flag on the way through
        yield 'vimeo' => [
            'https://player.vimeo.com/video/123456',
            'https://player.vimeo.com/video/123456?dnt=1',
        ];
        yield 'vimeo watch URL' => [
            'https://vimeo.com/123456',
            'https://player.vimeo.com/video/123456?dnt=1',
        ];
        yield 'dailymotion' => [
            'https://www.dailymotion.com/embed/video/x123',
            'https://www.dailymotion.com/embed/video/x123',
        ];
        yield 'tiktok' => [
            'https://www.tiktok.com/@someone/video/6860377138386734341',
            'https://www.tiktok.com/embed/v2/6860377138386734341',
        ];
    }

    #[DataProvider('provideUnaffectedUrls')]
    public function testToPrivacyEmbedUrlLeavesUnknownUrlsUntouched(?string $url): void
    {
        $extension = new VideoExtension();

        $this->assertSame($url, $extension->toPrivacyEmbedUrl($url));
    }

    public static function provideUnaffectedUrls(): iterable
    {
        // Left as-is even without "/embed/": whoever built this URL already made the privacy-safe choice themselves, not second-guessed by adding a path they may have deliberately omitted
        yield 'already nocookie bare path' => ['https://www.youtube-nocookie.com/abc123'];
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'not a URL' => ['not-a-url'];
        yield 'youtube.com watch URL without a v param' => ['https://www.youtube.com/watch?list=abc123'];
        // A self-hosted player, or any platform nobody declared - the filter has to be safe to apply to anything
        yield 'a site of its own' => ['https://975l.com/videos/kalaan.mp4'];
    }

    public function testGetFiltersRegistersPrivacyEmbedUrlFilter(): void
    {
        $extension = new VideoExtension();
        $filters = $extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('privacy_embed_url', $filters[0]->getName());
    }
}
