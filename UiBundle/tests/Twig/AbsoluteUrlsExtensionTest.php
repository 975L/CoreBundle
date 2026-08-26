<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\AbsoluteUrlsExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class AbsoluteUrlsExtensionTest extends TestCase
{
    public function testGetFiltersRegistersTheAbsoluteUrlsFilterAsHtmlSafe(): void
    {
        $filters = new AttributeExtension(AbsoluteUrlsExtension::class)->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('absolute_urls', $filters[0]->getName());

        // Without it Twig escapes the whole document it is handed, and the email holds &lt;p&gt; instead of <p>
        $this->assertSame(['html'], $filters[0]->getSafe(new \Twig\Node\TextNode('', 0)));
    }

    public function testRootRelativePicturesAndLinksAreRewritten(): void
    {
        $html = '<img src="/medias/shop/products/cover.webp"><a href="/pages/privacy">Privacy</a>';

        $this->assertSame(
            '<img src="https://site.test/medias/shop/products/cover.webp"><a href="https://site.test/pages/privacy">Privacy</a>',
            new AbsoluteUrlsExtension()->absolute($html, 'https://site.test'),
        );
    }

    public function testTrailingSlashOfTheBaseUrlDoesNotDoubleTheSeparator(): void
    {
        $this->assertSame(
            '<img src="https://site.test/logo.webp">',
            new AbsoluteUrlsExtension()->absolute('<img src="/logo.webp">', 'https://site.test/'),
        );
    }

    public function testWhatAlreadyNamesItsTargetIsLeftUntouched(): void
    {
        // An absolute url, a protocol-relative one, an anchor, a mailto and a data uri: none of them needs a host added
        $html = '<img src="https://site.test/logo.webp"><img src="//cdn.test/logo.webp"><a href="#top">Top</a>'
            . '<a href="mailto:contact@site.test">Mail</a><img src="data:image/gif;base64,R0lGOD">';

        $this->assertSame($html, new AbsoluteUrlsExtension()->absolute($html, 'https://site.test'));
    }

    public function testAnAttributeMerelyEndingInSrcIsNotRewritten(): void
    {
        $html = '<img data-src="/lazy.webp">';

        $this->assertSame($html, new AbsoluteUrlsExtension()->absolute($html, 'https://site.test'));
    }

    public function testNoConfiguredAddressLeavesTheDocumentAsItIs(): void
    {
        $html = '<img src="/logo.webp">';

        $this->assertSame($html, new AbsoluteUrlsExtension()->absolute($html, null));
        $this->assertSame($html, new AbsoluteUrlsExtension()->absolute($html, ''));
    }
}
