<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

// The kind's declared media types are what the upload is validated against (BlockType::addMediaSubForm): a hero
// template handling a background video is unreachable if the form refuses the file in the first place
class HeroMediaTypesTest extends TestCase
{
    public function testTheHeroAcceptsTheSameVideoFormatsAsTheVideoKind(): void
    {
        $hero = $this->tag('ui.block.hero');
        $video = $this->tag('ui.block.video');

        $accepted = array_filter(explode(',', $hero['media_types']), static fn (string $type): bool => str_starts_with($type, 'video/'));
        $expected = array_filter(explode(',', $video['media_types']), static fn (string $type): bool => str_starts_with($type, 'video/'));

        $this->assertSame(array_values($expected), array_values($accepted), 'Both kinds play their file through a plain <video>, so both must accept the very same formats.');
        // Images stay the ordinary case: they are laid out beside the text, painted behind it, or the video's still
        $this->assertStringContainsString('image/*', $hero['media_types']);
    }

    // A video behaves like nothing else the field takes - it fills the section by itself, muted - so the field says so
    public function testTheUploadFieldExplainsWhatAVideoDoes(): void
    {
        $this->assertSame('label.hero_media_help', $this->tag('ui.block.hero')['media_help'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private function tag(string $service): array
    {
        $services = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/services.yaml')['services'];
        $this->assertArrayHasKey($service, $services, sprintf('"%s" is no longer declared, this test no longer checks anything.', $service));

        return $services[$service]['tags'][0];
    }
}
