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

// A poster is what turns the player into click-to-play, and the one thing the placeholder must not do while it waits for that click is reach the platform - the whole point of importing the still into the site's own files (see VideoPosterImporter)
class VideoIframePosterTest extends TestCase
{
    private const TEMPLATE = 'templates/components/Video/Iframe.html.twig';

    // Hotlinking the platform's own still would be the very third-party request this placeholder exists to hold back
    public function testThePosterIsRenderedFromTheBlocksOwnMedia(): void
    {
        $block = 'templates/blocks/VideoIframe.html.twig';
        $path = \dirname(__DIR__, 2) . '/' . $block;
        $this->assertFileExists($path);
        $adapter = (string) file_get_contents($path);

        $this->assertStringContainsString('vich_uploader_asset(posterMedia)', $adapter, sprintf('"%s" no longer serves its poster from the site\'s own files.', $block));
        $this->assertStringNotContainsString('ytimg', $adapter);
    }

    // Decorative: the title beside it already names the video, and a screen reader gains nothing from "thumbnail of ..."
    public function testThePosterIsDecorativeAndLazy(): void
    {
        $template = $this->read();

        $this->assertMatchesRegularExpression(
            '/<img class="video-iframe-poster" src="\{\{ poster \}\}" alt="" aria-hidden="true" loading="lazy">/',
            $template,
            sprintf('"%s" renders its poster as content rather than as decoration.', self::TEMPLATE)
        );
    }

    // Only with a poster: without one there is nothing to press play over, and the controller loads the player on approach as it always has
    public function testThePlayButtonIsOnlyRenderedWithAPosterAndStartsHidden(): void
    {
        $template = $this->read();

        $this->assertMatchesRegularExpression('/\{% if poster %\}\s*<button type="button" class="video-iframe-play"[^>]*hidden>/', $template);
        $this->assertStringContainsString('data-videoiframe-target="play"', $template);
        $this->assertStringContainsString('data-action="videoIframe#play"', $template);
    }

    // The button carries no visible text, so its accessible name is the one thing standing between a screen reader and an unnamed button
    public function testThePlayButtonCarriesAnAccessibleName(): void
    {
        $this->assertStringContainsString(
            '<span class="sr-only">{{ \'label.video_iframe_play\'|trans({}, \'ui\') }}</span>',
            $this->read(),
            sprintf('"%s" leaves its play button unnamed.', self::TEMPLATE)
        );
    }

    // A bare play button over a still would make accepting third-party cookies look like pressing play, so the prompt is rendered whether or not there is a poster and hidden by the controller alone
    public function testTheConsentPromptIsRenderedWhateverThePoster(): void
    {
        $template = $this->read();

        $this->assertMatchesRegularExpression('/<div data-videoiframe-target="consent" class="video-iframe-prompt">/', $template);
        $this->assertDoesNotMatchRegularExpression('/\{% if poster %\}\s*<div data-videoiframe-target="consent"/', $template, sprintf('"%s" hides its consent prompt behind a poster, leaving consent to be given by a play button.', self::TEMPLATE));
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::TEMPLATE;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
