<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Video;

use c975L\UiBundle\Video\VideoPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoPlatformTest extends TestCase
{
    #[DataProvider('provideResolvableUrls')]
    public function testResolveReadsThePlatformAndTheIdOffAnyOfItsUrlForms(string $url, VideoPlatform $platform, string $id, array $carriedParams = []): void
    {
        $resolved = VideoPlatform::resolve($url);

        $this->assertNotNull($resolved, sprintf('"%s" resolves to nothing, so it would be framed as-is or refused.', $url));
        $this->assertSame($platform, $resolved->platform);
        $this->assertSame($id, $resolved->id);
        $this->assertSame($carriedParams, $resolved->carriedParams);
    }

    public static function provideResolvableUrls(): iterable
    {
        // What an editor copies out of their address bar while watching, which is the whole point of accepting urls rather than ids
        yield 'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube watch with a playlist before the id' => ['https://www.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ', ['list' => 'PL123']];
        // A playlist has no video id of its own, "videoseries" being the literal YouTube expects in its place - so the id alone would frame an empty player
        yield 'youtube playlist embed' => ['https://www.youtube.com/embed/videoseries?list=PL123', VideoPlatform::Youtube, 'videoseries', ['list' => 'PL123']];
        yield 'youtube watch with a timecode' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube embed' => ['https://youtube.com/embed/dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube nocookie embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
        yield 'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];

        yield 'tiktok watch' => ['https://www.tiktok.com/@example/video/6860377138386734341', VideoPlatform::Tiktok, '6860377138386734341'];
        yield 'tiktok embed v2' => ['https://www.tiktok.com/embed/v2/6860377138386734341', VideoPlatform::Tiktok, '6860377138386734341'];
        yield 'tiktok embed' => ['https://www.tiktok.com/embed/6860377138386734341', VideoPlatform::Tiktok, '6860377138386734341'];

        yield 'vimeo watch' => ['https://vimeo.com/123456789', VideoPlatform::Vimeo, '123456789'];
        yield 'vimeo channel' => ['https://vimeo.com/channels/staffpicks/123456789', VideoPlatform::Vimeo, '123456789'];
        yield 'vimeo player' => ['https://player.vimeo.com/video/123456789', VideoPlatform::Vimeo, '123456789'];
        // "h" is the access token of an unlisted video, without which the player answers with a private-video error
        yield 'vimeo unlisted' => ['https://vimeo.com/123456789?h=abc123def', VideoPlatform::Vimeo, '123456789', ['h' => 'abc123def']];

        yield 'dailymotion watch' => ['https://www.dailymotion.com/video/x8abcde', VideoPlatform::Dailymotion, 'x8abcde'];
        yield 'dailymotion embed' => ['https://www.dailymotion.com/embed/video/x8abcde', VideoPlatform::Dailymotion, 'x8abcde'];
        yield 'dailymotion geo player' => ['https://geo.dailymotion.com/player.html?video=x8abcde', VideoPlatform::Dailymotion, 'x8abcde'];
        yield 'dailymotion short link' => ['https://dai.ly/x8abcde', VideoPlatform::Dailymotion, 'x8abcde'];

        // Surrounding whitespace comes with any copy-paste, and is not a reason to refuse a valid url
        yield 'padded with whitespace' => ['  https://youtu.be/dQw4w9WgXcQ  ', VideoPlatform::Youtube, 'dQw4w9WgXcQ'];
    }

    #[DataProvider('provideUnresolvableUrls')]
    public function testResolveRefusesWhatItCannotIdentify(?string $url): void
    {
        $this->assertNull(VideoPlatform::resolve($url), sprintf('"%s" resolves to a platform it does not belong to.', $url ?? 'null'));
    }

    public static function provideUnresolvableUrls(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
        yield 'not a url' => ['not-a-url'];
        yield 'a site of its own' => ['https://example.com/videos/1'];
        // A bare path on the nocookie host is a deliberate choice made upstream, left alone rather than second-guessed
        yield 'nocookie bare path' => ['https://www.youtube-nocookie.com/dQw4w9WgXcQ'];
        // "/watch" with no video in it - a playlist page, which has no single video to frame
        yield 'youtube playlist page' => ['https://www.youtube.com/watch?list=PL123'];
        // Short links carry no id at all, only a redirect nobody can follow without asking TikTok from the server
        yield 'tiktok short link' => ['https://vm.tiktok.com/ZMabcdefg/'];
        // A host that merely ends with a platform's name is not that platform
        yield 'lookalike host' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ'];
        yield 'platform name in the path of another host' => ['https://evil.example.com/youtube.com/embed/dQw4w9WgXcQ'];
    }

    #[DataProvider('provideEmbedUrls')]
    public function testEmbedUrlIsBuiltFromTheIdAlone(VideoPlatform $platform, string $id, string $expected): void
    {
        $this->assertSame($expected, $platform->embedUrl($id));
    }

    public static function provideEmbedUrls(): iterable
    {
        // The privacy-first host, not youtube.com - what the whole rewrite exists for
        yield 'youtube' => [VideoPlatform::Youtube, 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'tiktok' => [VideoPlatform::Tiktok, '6860377138386734341', 'https://www.tiktok.com/embed/v2/6860377138386734341'];
        // "dnt=1" is Vimeo's own do-not-track flag
        yield 'vimeo' => [VideoPlatform::Vimeo, '123456789', 'https://player.vimeo.com/video/123456789?dnt=1'];
        yield 'dailymotion' => [VideoPlatform::Dailymotion, 'x8abcde', 'https://www.dailymotion.com/embed/video/x8abcde'];
    }

    // An id reaching this from an import or an old row must never be able to leave the platform's own url
    public function testEmbedUrlEscapesTheId(): void
    {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/abc%22%3E%3Cscript%3E',
            VideoPlatform::Youtube->embedUrl('abc"><script>')
        );
    }

    // A round trip: whatever form a url came in as, the id read off it rebuilds a playable embed url
    #[DataProvider('provideResolvableUrls')]
    public function testAResolvedUrlRebuildsItsPlatformEmbedUrl(string $url, VideoPlatform $platform, string $id, array $carriedParams = []): void
    {
        $this->assertSame($platform->embedUrl($id, $carriedParams), VideoPlatform::resolve($url)?->embedUrl());
    }

    // The two parameters a player cannot do without end up in the rebuilt url, on the right separator - Vimeo's already carries "dnt=1"
    #[DataProvider('provideEmbedUrlsWithCarriedParams')]
    public function testEmbedUrlCarriesTheParametersThePlayerNeeds(VideoPlatform $platform, string $id, array $carriedParams, string $expected): void
    {
        $this->assertSame($expected, $platform->embedUrl($id, $carriedParams));
    }

    public static function provideEmbedUrlsWithCarriedParams(): iterable
    {
        yield 'youtube playlist' => [VideoPlatform::Youtube, 'videoseries', ['list' => 'PL123'], 'https://www.youtube-nocookie.com/embed/videoseries?list=PL123'];
        yield 'vimeo unlisted' => [VideoPlatform::Vimeo, '123456789', ['h' => 'abc123def'], 'https://player.vimeo.com/video/123456789?dnt=1&h=abc123def'];
    }

    // Anything outside the whitelist stays out of what gets stored: playback options are the editor's to set again, tracking parameters have no business being framed
    #[DataProvider('provideUrlsWithParamsToDrop')]
    public function testCarriedParamsKeepsOnlyWhatThePlayerNeeds(VideoPlatform $platform, string $url, array $expected): void
    {
        $this->assertSame($expected, $platform->carriedParams($url));
    }

    public static function provideUrlsWithParamsToDrop(): iterable
    {
        yield 'youtube playback options' => [VideoPlatform::Youtube, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&autoplay=1&start=30', []];
        yield 'youtube campaign tag alongside a playlist' => [VideoPlatform::Youtube, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PL123&utm_source=news', ['list' => 'PL123']];
        yield 'vimeo empty hash' => [VideoPlatform::Vimeo, 'https://vimeo.com/123456789?h=', []];
        // A platform with nothing to carry reads no query at all
        yield 'dailymotion' => [VideoPlatform::Dailymotion, 'https://geo.dailymotion.com/player.html?video=x8abcde&list=PL123', []];
        yield 'tiktok' => [VideoPlatform::Tiktok, 'https://www.tiktok.com/@example/video/6860377138386734341?h=abc', []];
        yield 'no query at all' => [VideoPlatform::Youtube, 'https://youtu.be/dQw4w9WgXcQ', []];
    }

    public function testEveryPlatformDeclaresTheOriginItsPlayerIsFramedFrom(): void
    {
        foreach (VideoPlatform::cases() as $platform) {
            $origins = $platform->cspOrigins();
            $this->assertNotEmpty($origins, sprintf('"%s" declares no CSP origin, so its player is an empty frame in production.', $platform->value));

            // The embed url has to be covered by one of the declared origins, or the policy allows the wrong host
            $embedOrigin = parse_url($platform->embedUrl('id'), PHP_URL_SCHEME) . '://' . parse_url($platform->embedUrl('id'), PHP_URL_HOST);
            $this->assertContains($embedOrigin, $origins, sprintf('"%s" is framed from "%s", which none of its declared CSP origins covers.', $platform->value, $embedOrigin));
        }
    }

    public function testAllCspOriginsGathersEveryPlatformWithoutDuplicates(): void
    {
        $origins = VideoPlatform::allCspOrigins();

        $this->assertSame(array_values(array_unique($origins)), $origins);
        foreach (VideoPlatform::cases() as $platform) {
            foreach ($platform->cspOrigins() as $origin) {
                $this->assertContains($origin, $origins);
            }
        }
    }

    // Every origin has to be a bare scheme://host - a path or a trailing slash in a frame-src is silently ignored by some browsers and narrows the directive in others
    public function testCspOriginsAreBareOrigins(): void
    {
        foreach (VideoPlatform::allCspOrigins() as $origin) {
            $this->assertMatchesRegularExpression('#^https://[a-z0-9.-]+$#', $origin, sprintf('"%s" is not a bare origin.', $origin));
        }
    }

    public function testAspectRatioIsPortraitOnlyWhereThePlatformIs(): void
    {
        $this->assertSame('9 / 16', VideoPlatform::Tiktok->aspectRatio());
        $this->assertSame('16 / 9', VideoPlatform::Youtube->aspectRatio());
        $this->assertSame('16 / 9', VideoPlatform::Vimeo->aspectRatio());
        $this->assertSame('16 / 9', VideoPlatform::Dailymotion->aspectRatio());
    }

    // The values are what consuming bundles store in their own columns (see GalleryMedia::mediaType), so they are a contract - renaming one is a data migration, not a rename
    public function testValuesAreTheStoredContract(): void
    {
        $this->assertSame(['youtube', 'tiktok', 'vimeo', 'dailymotion'], VideoPlatform::values());
    }

    // Best first: "maxresdefault" is the only 16:9 still big enough for a grid tile, and is missing on plenty of videos
    public function testPosterUrlsAreOrderedBestFirstForYoutube(): void
    {
        $this->assertSame([
            'https://i.ytimg.com/vi/lXqKJvMxEdo/maxresdefault.jpg',
            'https://i.ytimg.com/vi/lXqKJvMxEdo/hqdefault.jpg',
        ], VideoPlatform::Youtube->posterUrls('lXqKJvMxEdo'));
    }

    // No guessable address means no candidate rather than a guessed one: these three hand their stills out through an API call
    public function testPosterUrlsAreEmptyForPlatformsServingNoGuessableStill(): void
    {
        $this->assertSame([], VideoPlatform::Tiktok->posterUrls('1234567890'));
        $this->assertSame([], VideoPlatform::Vimeo->posterUrls('123456'));
        $this->assertSame([], VideoPlatform::Dailymotion->posterUrls('x8abcde'));
    }

    // An id reaches the url encoded, same as in embedUrl() - it comes from a pasted url and is matched loosely on purpose
    public function testPosterUrlsEncodeTheId(): void
    {
        $this->assertSame([
            'https://i.ytimg.com/vi/a%2Fb/maxresdefault.jpg',
            'https://i.ytimg.com/vi/a%2Fb/hqdefault.jpg',
        ], VideoPlatform::Youtube->posterUrls('a/b'));
    }

    // Hotlinking a still would be the very third-party request the consent placeholder exists to hold back, so the host has to be the cookieless static one it is imported from server-side
    public function testPosterUrlsUseTheCookielessStaticHost(): void
    {
        foreach (VideoPlatform::cases() as $platform) {
            foreach ($platform->posterUrls('some-id') as $url) {
                $this->assertStringStartsWith('https://i.ytimg.com/', $url);
            }
        }
    }
}
