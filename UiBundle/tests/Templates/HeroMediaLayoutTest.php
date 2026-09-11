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

// The field picks between two layouts of the very same medias, so each one is rendered rather than read as text
class HeroMediaLayoutTest extends TestCase
{
    public function testGridShowsEveryMediaAtOnceAndCarriesNoSlideCount(): void
    {
        $html = $this->render(['mediaLayout' => 'grid']);

        $this->assertStringContainsString('class="hero__media hero__media--grid"', $html);
        $this->assertStringNotContainsString('hero__media--slideshow', $html);
        // No timing to read: the count only ever fed the crossfade's animation-duration
        $this->assertStringNotContainsString('data-count', $html);
        $this->assertSame(3, substr_count($html, '<img src='));
    }

    public function testSlideshowKeepsItsSlideCount(): void
    {
        $html = $this->render(['mediaLayout' => 'slideshow']);

        $this->assertStringContainsString('class="hero__media hero__media--slideshow" data-count="3"', $html);
        $this->assertStringNotContainsString('hero__media--grid', $html);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function nonGridProvider(): array
    {
        return [
            'field never filled in' => [[]],
            'field left empty' => [['mediaLayout' => '']],
            'value no layout matches' => [['mediaLayout' => 'whatever']],
        ];
    }

    // The value is matched, never interpolated: anything but "grid" is the crossfade every hero stored before the field existed asks for
    #[\PHPUnit\Framework\Attributes\DataProvider('nonGridProvider')]
    public function testAnythingOtherThanGridRendersTheSlideshow(array $context): void
    {
        $html = $this->render($context);

        $this->assertStringContainsString('hero__media--slideshow', $html);
        $this->assertStringNotContainsString('hero__media--whatever', $html);
    }

    // The background mode paints the first media behind the whole section, so neither layout is reached
    public function testABackgroundImageDropsBothLayouts(): void
    {
        $html = $this->render(['mediaLayout' => 'grid', 'hasBackgroundImage' => true]);

        $this->assertStringContainsString('class="hero__bg"', $html);
        $this->assertStringNotContainsString('hero__media', $html);
    }

    // A hero rotating through works of its own is content: each media states its own alternative rather than the blanket aria-hidden the layout used to print
    public function testEachMediaCarriesItsOwnAlt(): void
    {
        $html = $this->render([], [
            (object) ['alt' => 'Portrait au graphite', 'intrinsicWidth' => 160, 'intrinsicHeight' => 160],
            (object) ['alt' => 'Tigre au graphite', 'intrinsicWidth' => 160, 'intrinsicHeight' => 160],
        ]);

        $this->assertStringContainsString('alt="Portrait au graphite"', $html);
        $this->assertStringContainsString('alt="Tigre au graphite"', $html);
        $this->assertStringNotContainsString('aria-hidden', $html);
    }

    // Every hero stored before the field was filled: an empty alt still doubles as aria-hidden, so checkers read it as decorative rather than as a missing alternative
    public function testAMediaLeftWithoutAnAltStaysDecorative(): void
    {
        $html = $this->render([], [
            (object) ['alt' => 'Portrait au graphite', 'intrinsicWidth' => 160, 'intrinsicHeight' => 160],
            (object) ['alt' => null, 'intrinsicWidth' => 160, 'intrinsicHeight' => 160],
        ]);

        $this->assertStringContainsString('alt="" aria-hidden="true"', $html);
        $this->assertSame(1, substr_count($html, 'aria-hidden'));
    }

    // Twig resolves these at compile time, so they must exist even when never reached
    private function render(array $context, ?array $medias = null): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trix_inline', static fn (?string $value): string => (string) $value, ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => (bool) $value));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (mixed $media): string => '/media/logo.webp'));

        $medias ??= array_fill(0, 3, (object) ['alt' => 'Une œuvre', 'intrinsicWidth' => 160, 'intrinsicHeight' => 160]);

        return $twig->render('components/Hero/Hero.html.twig', $context + ['title' => 'Un titre', 'medias' => $medias]);
    }
}
