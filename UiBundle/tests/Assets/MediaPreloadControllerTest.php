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

// A player printed with preload="none" gets its first frame or its duration only once on screen, from this controller
class MediaPreloadControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/media-preload.js';

    // Raised once the player intersects, then the observer is let go
    public function testTheControllerRaisesThePreloadOnceOnScreen(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('new IntersectionObserver(', $script);
        $this->assertStringContainsString('isIntersecting', $script);
        $this->assertStringContainsString('this.element.preload = "metadata"', $script);
        $this->assertStringContainsString('this.observer.disconnect()', $script);
    }

    // Lazily registered under the identifier the Video and Audio components write
    public function testTheControllerIsRegisteredUnderTheIdentifierTheMarkupWrites(): void
    {
        $this->assertStringContainsString("mediaPreload: () => import('./js/media-preload.js')", $this->read('assets/controllers.js'));
        $this->assertStringContainsString('data-controller="mediaPreload"', $this->read('templates/components/Video/Video.html.twig'));
        $this->assertStringContainsString('data-controller="mediaPreload"', $this->read('templates/components/Audio/Audio.html.twig'));
    }

    private function read(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, half of the mechanism this test checks is gone.', $file));

        return (string) file_get_contents($path);
    }
}
