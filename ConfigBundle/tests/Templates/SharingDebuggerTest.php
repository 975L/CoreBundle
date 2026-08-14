<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The note every screen deciding a share image carries: a network caches that preview under the page's url from the first share on, so an image chosen later needs a re-scrape to ever show up
class SharingDebuggerTest extends TestCase
{
    // The whole point of the note - an admin having nothing to click would be left with the sentence and no way to act on it
    public function testItLinksTheDebuggerOnTheUrlItIsHanded(): void
    {
        $html = $this->render('/animaux');

        $this->assertStringContainsString('https://developers.facebook.com/tools/debug/?q=https%3A%2F%2Fexample.com%2Fanimaux', $html);
        $this->assertStringContainsString('label.sharing_debugger_help', $html);
        $this->assertStringContainsString('label.sharing_debugger_link', $html);
    }

    // Facebook only ever reads an absolute url, and path() hands back a path - made absolute here so no caller has to remember to
    public function testThePathIsMadeAbsolute(): void
    {
        $this->assertStringNotContainsString('?q=%2Fanimaux', $this->render('/animaux'));
    }

    // A screen including it for a row carrying no url yet renders nothing rather than a link to the site root
    public function testItRendersNothingWithoutAnUrl(): void
    {
        $this->assertSame('', trim($this->render(null)));
        $this->assertSame('', trim($this->render('')));
    }

    // The trans filter is the framework's and absolute_url() Symfony's HttpFoundation extension - neither is booted here, so both are stubbed and the shipped source read as it is
    private function render(?string $url): string
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/management/_sharing_debugger.html.twig');

        $twig = new Environment(new ArrayLoader(['debugger' => $source]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => 'https://example.com' . $path));

        return $twig->render('debugger', null === $url ? [] : ['url' => $url]);
    }
}
