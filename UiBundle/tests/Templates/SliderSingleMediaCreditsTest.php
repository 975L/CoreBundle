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
use Twig\TwigFunction;

// The two mentions drawn over a media used to reach the slides only, so a fiche carrying a single photograph published it with neither its credit nor its rights notice - the attribution a license asks for, dropped by the count of images on the page
class SliderSingleMediaCreditsTest extends TestCase
{
    public function testASingleMediaKeepsItsCredits(): void
    {
        $html = $this->render([$this->media(['credits' => 'Michel Germain'])]);

        $this->assertStringContainsString('class="slider-credits"', $html);
        $this->assertStringContainsString('Michel Germain', $html);
    }

    public function testASingleMediaKeepsItsRightsNotice(): void
    {
        $html = $this->render([$this->media(['rightsReserved' => true])]);

        $this->assertStringContainsString('class="slider-rights-reserved"', $html);
    }

    // Both mentions are positioned over the media, so they need the box this branch had none of: without it they anchor to whatever positioned ancestor the page happens to carry
    public function testTheMentionsSitInABoxOfTheirOwn(): void
    {
        $html = $this->render([$this->media(['credits' => 'Michel Germain'])]);

        $this->assertStringContainsString('class="slider-single-media"', $html);
        $this->assertSame(1, substr_count($html, 'slider-single-media'));
    }

    // A media carrying neither writes neither: the box is still there, an empty band over a photograph being worse than no band
    public function testAMediaCarryingNeitherWritesNoBand(): void
    {
        $html = $this->render([$this->media()]);

        $this->assertStringNotContainsString('slider-credits', $html);
        $this->assertStringNotContainsString('slider-rights-reserved', $html);
    }

    // The video branch of the same count: a film credited to someone is credited too
    public function testASingleVideoKeepsItsCredits(): void
    {
        $html = $this->render([$this->media(['mimeType' => 'video/mp4', 'credits' => 'Michel Germain'])]);

        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('class="slider-credits"', $html);
    }

    // What the fix was measured against: the slides already carried both, and still do
    public function testSeveralMediasStillCarryTheirOwn(): void
    {
        $html = $this->render([
            $this->media(['credits' => 'Michel Germain']),
            $this->media(['credits' => 'Maurice Bleicher']),
        ]);

        $this->assertSame(2, substr_count($html, 'class="slider-credits"'));
        $this->assertStringNotContainsString('slider-single-media', $html);
    }

    /** @param array<string, mixed> $values */
    private function media(array $values = []): object
    {
        return (object) ($values + [
            'alt' => 'Un insigne',
            'mimeType' => 'image/webp',
            'label' => null,
            'width' => '485',
            'height' => '666',
            'cssClasses' => [],
            'above' => false,
            'credits' => null,
            'rightsReserved' => null,
        ]);
    }

    // The bare environment the component renderer would otherwise bring. The nested <twig:...> call is left as text, which is all these assertions need - the two mentions are plain markup of this very template
    /** @param list<object> $media */
    private function render(array $media): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trans', static fn (?string $key): string => (string) $key));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (mixed $value): string => '/medias/insigne.webp'));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));

        return $twig->render('components/Slider/Slider.html.twig', ['media' => $media, 'id' => 'slider']);
    }
}
