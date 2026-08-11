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

// The component's title does two jobs, and only one of them is optional: it names the player to a screen reader, and it heads the figure on screen
// A page laying out its own heading around this component (c975l/gallery-bundle's media page) turns the second off - and must not be able to turn the first off with it, an iframe with no accessible name being what a screen reader announces as nothing at all
class VideoIframeCaptionTest extends TestCase
{
    private const string TEMPLATE = 'templates/components/Video/Iframe.html.twig';

    public function testTheCaptionIsShownUnlessItIsTurnedOff(): void
    {
        $template = $this->read();

        $this->assertStringContainsString('{% set caption = caption is defined ? caption|to_bool : true %}', $template, sprintf('"%s" no longer defaults its caption to shown, so every existing block loses its heading.', self::TEMPLATE));
        $this->assertStringContainsString('{% if caption and title is defined and title %}', $template);
        $this->assertStringContainsString('{% if caption and description is defined and description %}', $template);
    }

    // The value the controller reads to name the iframe - never behind the caption flag
    public function testTheTitleAlwaysReachesThePlayer(): void
    {
        $this->assertMatchesRegularExpression(
            '/data-videoiframe-title-value="\{\{ title\|default\(\'\'\) \}\}"/',
            $this->read(),
            sprintf('"%s" no longer passes its title to the controller, leaving the iframe with no accessible name.', self::TEMPLATE)
        );
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::TEMPLATE;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
