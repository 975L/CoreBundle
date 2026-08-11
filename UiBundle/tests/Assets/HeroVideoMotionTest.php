<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// A hero's background video autoplays with no control of any kind printed beside it, so a visitor asking for reduced motion has no way out of it but this controller (WCAG 2.2.2) - and it only has that say because the markup hands the decision over to it instead of writing an "autoplay" attribute no script can take back
class HeroVideoMotionTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/hero-video.js';

    // The attribute is honored before any script runs, and no preference read afterwards can undo the motion
    public function testTheMarkupNeverAutoplaysTheBackgroundVideoByItself(): void
    {
        $component = $this->read('templates/components/Hero/Hero.html.twig');
        // Comments name the attribute to explain why it is not written, which is not markup
        $markup = (string) preg_replace('/\{#.*?#\}/s', '', $component);

        $this->assertStringContainsString('<video class="hero__bg hero__bg--video" data-controller="heroVideo"', $markup);
        $this->assertStringNotContainsString('autoplay', $markup);
    }

    public function testTheControllerReadsThePreferenceAndPausesRatherThanHides(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('matchMedia("(prefers-reduced-motion: reduce)")', $script);
        $this->assertStringContainsString('.pause()', $script);
        // Paused, never taken off the page: the frame it stops on goes on filling the section, where hiding the video would bare the overlay under it whenever the editor uploaded no image beside it
        $this->assertStringNotContainsString('style.display', $script);
        $this->assertStringNotContainsString('classList', $script);
    }

    // A preference turned on while the page is open, or a hero connected on a Turbo visit, must be answered too
    public function testTheControllerFollowsThePreferenceForAsLongAsItIsConnected(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('addEventListener("change"', $script);
        $this->assertStringContainsString('removeEventListener("change"', $script);
    }

    // Lazily registered, and only for a document actually holding one - the identifier is what pairs the two halves
    public function testTheControllerIsRegisteredUnderTheIdentifierTheMarkupWrites(): void
    {
        $this->assertStringContainsString("heroVideo: () => import('./js/hero-video.js')", $this->read('assets/controllers.js'));
    }

    // The video is laid out by the very same rule as the image it covers, nothing of its own to declare
    public function testTheVideoIsCoveredByTheSharedBackgroundRule(): void
    {
        foreach (['styles.css', 'styles.min.css'] as $file) {
            $css = (string) preg_replace('/\s+/', '', $this->read('public/css/' . $file));

            $this->assertStringContainsString('.hero.hero--has-bg.hero__bg{', $css);
            $this->assertStringNotContainsString('img.hero__bg', $css);
        }
    }

    // object-fit: cover crops the shot to whatever height the section ends up with, and a hero holding a short title - or none at all - is only as tall as its own paddings, leaving a strip of video instead of a picture
    public function testTheSectionIsGivenAHeightOfItsOwnToShowTheVideoIn(): void
    {
        foreach (['styles.css', 'styles.min.css'] as $file) {
            $css = (string) preg_replace('/\s+/', '', $this->read('public/css/' . $file));

            $this->assertStringContainsString('.hero.hero--has-bg:has(.hero__bg--video){', $css);
            // Retunable from a site's own theme.css, with the bundle's value as the fallback every rule here carries
            $this->assertStringContainsString('min-height:var(--hero-video-min-height,70vh)', $css);
            // The text is centered in the room that height opens, rather than pinned to the top of it
            $this->assertStringContainsString('justify-content:center', $css);
        }
    }

    private function read(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, half of the mechanism this test checks is gone.', $file));

        return (string) file_get_contents($path);
    }
}
