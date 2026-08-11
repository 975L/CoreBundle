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

// The banner's image and height are per-instance and no class can carry them, so they go into a <style> element - the only inline form a nonce ever authorizes, an attribute never being one. The nonce itself is not written here: this markup is cached verbatim by BlockExtension, which swaps the "data-ui-nonce" marker for the nonce of the response being built (see BlockExtension::applyNonce), a real one frozen into a cache entry matching nothing afterwards. What each assertion locks is that the values still reach the page, and that they reach it in that form
class BannerTitleStyleTest extends TestCase
{
    // The rule and the element it addresses are written in the same render, so an id drifting from its selector would leave the banner with no image at all
    public function testTheBannerIsStyledByAMarkedElementAddressingItsOwnId(): void
    {
        $html = $this->render(['title' => 'Un titre', 'src' => 'img/banner.jpg']);

        $this->assertSame(1, preg_match('/<style data-ui-nonce>#(banner-title-\d+) \{ ([^}]+) \}<\/style>/', $html, $matches), 'The banner no longer carries its style element.');
        $this->assertStringContainsString('id="' . $matches[1] . '"', $html, 'The rule addresses an id the banner does not carry.');
        $this->assertStringContainsString('background-image:url(', $matches[2]);
    }

    // A nonce authorizes an element, never an attribute: a style="" here would be dropped by the browser on every site whose layout nonces style-src
    public function testTheBannerCarriesNoStyleAttribute(): void
    {
        $html = $this->render(['title' => 'Un titre', 'src' => 'img/banner.jpg', 'maxHeight' => 320]);

        $this->assertStringNotContainsString('style="', $html, 'The banner writes its styling as an attribute again, which a nonced style-src drops.');
    }

    // Both values share the one element, the height being as per-instance as the image
    public function testTheMaxHeightIsWrittenInThatSameRule(): void
    {
        $html = $this->render(['title' => 'Un titre', 'src' => 'img/banner.jpg', 'maxHeight' => 320]);

        $this->assertSame(1, preg_match_all('/<style /', $html), 'The banner emits more than one style element.');
        $this->assertStringContainsString('max-height:320px', $html);
    }

    // Escaped for CSS, not for HTML: the apostrophe closing the url() is what a path could break the rule out of, and an entity would reach the browser undecoded inside a stylesheet
    public function testTheImagePathIsEscapedForCss(): void
    {
        $html = $this->render(['title' => 'Un titre', 'src' => "img/a'b.jpg"]);

        $this->assertStringContainsString('\27 ', $html, 'The apostrophe is no longer CSS-escaped, so a path can close the url() and write its own declarations.');
        $this->assertStringNotContainsString('&#039;', $html, 'The value is HTML-escaped, and a stylesheet does not decode entities.');
    }

    // Nothing to declare, nothing to address: a banner with neither image nor height gets no element and no id
    public function testABareBannerEmitsNoStyleElementAndNoId(): void
    {
        $html = $this->render(['title' => 'Un titre']);

        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('id="banner-title-', $html);
        $this->assertStringContainsString('<div class="banner-title">', $html);
    }

    // "asset" comes from the app and is not booted here; "csp_nonce" is deliberately absent, the template no longer calling it
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/build/' . $path));

        return $twig->render('components/Banner/Title.html.twig', $context);
    }
}
