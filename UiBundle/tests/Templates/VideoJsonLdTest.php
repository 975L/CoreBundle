<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// A video result needs a name, a thumbnail and an upload date: the block publishes a VideoObject only when it truly holds all three, never a date or a cover it would have to make up
class VideoJsonLdTest extends TestCase
{
    private const string TEMPLATE = 'templates/blocks/Video.html.twig';

    public function testTheVideoObjectIsGatedOnTitleCoverAndFileDate(): void
    {
        $this->assertStringContainsString("{% if title|default('') and poster and video.updatedAt %}", $this->read());
    }

    // The file's own date: the page's would claim the video changed each time a word around it did
    public function testTheUploadDateIsTheFilesOwn(): void
    {
        $this->assertStringContainsString("'uploadDate': video.updatedAt|date('c')", $this->read());
    }

    // Encoded like every other graph of the bundles, not with flags of its own
    public function testThePayloadGoesThroughTheSharedEncoder(): void
    {
        $template = $this->read();

        $this->assertStringContainsString('{{ json_ld(videoJsonLd) }}', $template);
        $this->assertStringNotContainsString('json_encode', $template);
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::TEMPLATE;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // An embedded video is dated by its platform, stored in the block by the command - never by the block's own dates
    public function testAnEmbeddedVideoIsGatedOnItsPlatformDate(): void
    {
        $path = \dirname(__DIR__, 2) . '/templates/blocks/VideoIframe.html.twig';
        $template = (string) file_get_contents($path);

        $this->assertStringContainsString("{% if title|default('') and posterMedia and posterMedia.filename and uploadDate|default('') %}", $template);
        $this->assertStringContainsString("'uploadDate': uploadDate,", $template);
        $this->assertStringContainsString('{{ json_ld(videoJsonLd) }}', $template);
    }

    // Google expects the player's url, not the "/watch?v=" or share link an editor pasted
    public function testAnEmbeddedVideoPublishesThePlayersUrl(): void
    {
        $path = \dirname(__DIR__, 2) . '/templates/blocks/VideoIframe.html.twig';
        $template = (string) file_get_contents($path);

        $this->assertStringContainsString("'embedUrl': src|privacy_embed_url,", $template);
    }
}
