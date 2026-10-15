<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Twig\BoolExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// A "-highres.webp" missing on disk is never linked to: without javascript, or on a Ctrl+click, the link would lead to a 404
class ImageZoomHighresTest extends TestCase
{
    public function testAnExistingHighresIsLinked(): void
    {
        $html = $this->render(['src' => 'medias/photo.webp', 'highres' => 'medias/photo-highres.webp'], true);

        $this->assertStringContainsString('href="/medias/photo-highres.webp"', $html);
        $this->assertStringContainsString('aria-label="label.see_high_resolution"', $html);
    }

    // The stored file is opened instead, and announced for what it is - no bigger file is coming
    public function testAMissingHighresFallsBackOnTheStoredFile(): void
    {
        $html = $this->render(['src' => 'medias/photo.webp', 'highres' => 'medias/photo-highres.webp'], false);

        $this->assertStringContainsString('href="/medias/photo.webp"', $html);
        $this->assertStringNotContainsString('-highres', $html);
        $this->assertStringContainsString('aria-label="label.enlarge_image"', $html);
    }

    // The slider hands its own class to the link, the one its styles and its click handler already know
    public function testTheLinkCarriesTheCallersClass(): void
    {
        $html = $this->render(['src' => 'medias/photo.webp', 'highres' => 'medias/photo.webp', 'linkClass' => 'slider-img-link'], true);

        $this->assertStringContainsString('class="image-zoom__link slider-img-link"', $html);
    }

    public function testWithoutLinkClassTheLinkKeepsItsOwnClassOnly(): void
    {
        $html = $this->render(['src' => 'medias/photo.webp', 'highres' => 'medias/photo.webp'], true);

        $this->assertStringContainsString('class="image-zoom__link"', $html);
    }

    // The bare environment the component renderer would otherwise bring, asset_exists() answering what the disk would
    /** @param array<string, mixed> $context */
    private function render(array $context, bool $exists): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trans', static fn (?string $key): string => (string) $key));
        $twig->addFilter(new TwigFilter('to_bool', new BoolExtension()->toBool(...)));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . ltrim($path, '/')));
        $twig->addFunction(new TwigFunction('asset_exists', static fn (string $path): bool => $exists));

        return $twig->render('components/Image/Zoom.html.twig', $context);
    }
}
