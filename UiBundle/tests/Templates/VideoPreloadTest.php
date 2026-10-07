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

// A video with a poster fetches nothing before play, one without keeps its metadata for a first frame instead of a black box
class VideoPreloadTest extends TestCase
{
    public function testAVideoWithAPosterPreloadsNothing(): void
    {
        $html = $this->render(['src' => '/v.mp4', 'type' => 'video/mp4', 'poster' => '/p.webp']);

        $this->assertStringContainsString('preload="none"', $html);
    }

    public function testAVideoWithoutAPosterPreloadsItsMetadata(): void
    {
        $html = $this->render(['src' => '/v.mp4', 'type' => 'video/mp4']);

        $this->assertStringContainsString('preload="metadata"', $html);
    }

    public function testAPriorityVideoPreloadsItsPosterFirst(): void
    {
        $html = $this->render(['src' => '/v.mp4', 'type' => 'video/mp4', 'poster' => '/p.webp', 'priority' => true]);

        $this->assertStringContainsString('<link rel="preload" as="image" href="/p.webp" fetchpriority="high">', $html);
    }

    public function testAVideoFurtherDownPreloadsNoPoster(): void
    {
        $html = $this->render(['src' => '/v.mp4', 'type' => 'video/mp4', 'poster' => '/p.webp']);

        $this->assertStringNotContainsString('rel="preload"', $html);
    }

    // Played by the heroVideo controller once on screen, never by the attribute that would fetch the whole file with the page
    public function testAnAutoplayVideoIsLeftToTheControllerThatPlaysItOnScreen(): void
    {
        $html = $this->render(['src' => '/v.mp4', 'type' => 'video/mp4', 'autoplay' => true, 'muted' => true]);

        $this->assertStringContainsString('data-controller="heroVideo"', $html);
        $this->assertStringNotContainsString(' autoplay', $html);
    }

    // The component alone, its two filters stubbed: only the <video> attributes are under test
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates/components/Video'));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => (bool) $value));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));

        return $twig->render('Video.html.twig', $context);
    }
}
