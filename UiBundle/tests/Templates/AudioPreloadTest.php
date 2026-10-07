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
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

// An audio player fetches nothing with the page, its duration asked for once on screen
class AudioPreloadTest extends TestCase
{
    public function testAnAudioPreloadsNothingWithThePage(): void
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates/components/Audio'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));

        $html = $twig->render('Audio.html.twig', ['src' => '/a.mp3', 'type' => 'audio/mpeg']);

        $this->assertStringContainsString('preload="none" data-controller="mediaPreload"', $html);
    }
}
