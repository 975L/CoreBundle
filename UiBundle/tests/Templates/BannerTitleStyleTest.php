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
use Twig\TwigFunction;

// The banner writes no CSS of its own at all: the picture is an element carrying its own src, and the height is one of three steps naming a class. What it used to write was a <style> element - the only inline form a nonce ever authorizes, an attribute never being one - and the HTML spec allows that element nowhere but the <head>, which a block rendered mid-page cannot reach. What each assertion locks is that both values still reach the page, and that they reach it in that form
class BannerTitleStyleTest extends TestCase
{
    // A <style> anywhere in this markup is invalid HTML wherever the block is dropped, the element being metadata content
    public function testTheBannerWritesNoStyleElementAndNoStyleAttribute(): void
    {
        $html = $this->render(['title' => 'Un titre', 'src' => 'img/banner.jpg', 'height' => 'medium']);

        $this->assertStringNotContainsString('<style', $html, 'The banner writes a style element again, which is invalid outside the <head>.');
        $this->assertStringNotContainsString('style="', $html, 'The banner writes its styling as an attribute, which a nonced style-src drops.');
    }

    // The picture is what the block holds, so it is an <img> carrying its own alt - where a background had to be described through the role="img"/aria-label the banner used to wear
    public function testThePictureIsAnImageElementCarryingItsOwnAlt(): void
    {
        $html = $this->render(['title' => 'Un titre', 'src' => 'img/banner.jpg', 'alt' => 'Un paysage']);

        $this->assertStringContainsString('<img src="/build/img/banner.jpg" alt="Un paysage" class="banner-title-img">', $html);
        $this->assertStringNotContainsString('role="img"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }

    // Decorative rather than undescribed: an alt is what tells a screen reader to skip the picture, and its absence what makes it announce the file name instead
    public function testAPictureWithNoAltIsStillGivenAnEmptyOne(): void
    {
        $this->assertStringContainsString('alt=""', $this->render(['title' => 'Un titre', 'src' => 'img/banner.jpg']));
    }

    // The height names a class off a closed list, so nothing an editor stores against the block ever writes CSS
    public function testTheHeightIsOneOfThreeStepsWrittenAsAClass(): void
    {
        foreach (['small', 'medium', 'large'] as $step) {
            $html = $this->render(['title' => 'Un titre', 'height' => $step]);
            $this->assertStringContainsString('class="banner-title banner-title--height-' . $step . '"', $html);
        }
    }

    // A value off the list is a value the stylesheet knows nothing about: it names no class rather than writing one of its own - a banner stored back when this field took a pixel value lands here
    public function testAValueOutsideTheListNamesNoClassAtAll(): void
    {
        foreach (['320', 'small; color:red', ''] as $stored) {
            $html = $this->render(['title' => 'Un titre', 'height' => $stored]);
            $this->assertStringNotContainsString('banner-title--height', $html, sprintf('"%s" wrote a class of its own.', $stored));
        }
    }

    // Nothing to show: a banner with neither picture nor height is the overlay and its title, and nothing else
    public function testABareBannerCarriesNeitherImageNorHeightClass(): void
    {
        $html = $this->render(['title' => 'Un titre']);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('class="banner-title "', $html);
    }

    // "asset" comes from the app and is not booted here; "csp_nonce" is deliberately absent, the template no longer calling it
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/build/' . $path));

        return $twig->render('components/Banner/Title.html.twig', $context);
    }
}
