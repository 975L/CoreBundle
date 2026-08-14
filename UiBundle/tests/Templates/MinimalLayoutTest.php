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
use Twig\Loader\ArrayLoader;

// This layout is what a site running Config+Ui plus a satellite bundle (shop, book, gallery) actually serves - it used to be a bare shell with no favicon, no theme and no cookie banner, so such a site could not go live on it at all. What each assertion locks is one thing that shipping without would be a defect, read out of the template that actually ships rather than restated from a copy of it
class MinimalLayoutTest extends TestCase
{
    private function layout(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/layout.html.twig');
    }

    // The admin-editable theme reaches the page two ways: the compiled custom properties (a stylesheet, see ThemeVariablesStylesheetProvider) and this attribute, which is the only one the template itself can carry
    public function testTheThemeModeDrivesTheDataThemeAttribute(): void
    {
        $this->assertMatchesRegularExpression(
            '/<html[^>]*\{% if config\(\'theme-mode\'\) in \[\'light\', \'dark\'\] %\} data-theme="\{\{ config\(\'theme-mode\'\) \}\}"/',
            $this->layout()
        );
    }

    // The site graphics are Media rows carrying a role (see SiteGraphicCrudController) - a site with no favicon at all is the single most visible thing this layout used to be missing
    public function testTheSiteGraphicsAreRendered(): void
    {
        $layout = $this->layout();

        foreach (['favicon', 'apple-touch-icon', 'og-image', 'logo'] as $role) {
            $this->assertStringContainsString(\sprintf("site_media('%s')", $role), $layout, $role . ' is never read');
        }

        $this->assertStringContainsString('<link rel="icon"', $layout);
        $this->assertStringContainsString('<link rel="apple-touch-icon"', $layout);
        $this->assertStringContainsString('<meta property="og:image"', $layout);
    }

    // Without these a link shared on a social network renders as a bare url
    public function testTheShareTagsAreRendered(): void
    {
        $layout = $this->layout();

        foreach (['og:title', 'og:type', 'og:site_name', 'og:url'] as $tag) {
            $this->assertStringContainsString('property="' . $tag . '"', $layout, $tag . ' is missing');
        }

        $this->assertStringContainsString('<link rel="canonical"', $layout);
    }

    // A share read out rather than displayed has only this to go on, and the media library already holds it as a column - the template that set the image itself says it under "ogImageAlt"
    public function testTheShareImageStatesWhatItShows(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('<meta property="og:image:alt"', $layout);
        $this->assertStringContainsString('ogImageAlt', $layout);
        $this->assertStringContainsString('ogImageMedia.alt', $layout);
    }

    // A network reading them keeps the thumbnail's room before the file loads - written only for a media holding both, a guessed size being worse than none
    public function testTheShareImageStatesItsDimensionsWhenTheyAreKnown(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('<meta property="og:image:width" content="{{ ogImageMedia.width }}">', $layout);
        $this->assertStringContainsString('<meta property="og:image:height" content="{{ ogImageMedia.height }}">', $layout);
        $this->assertStringContainsString('ogImageMedia is not null and ogImageMedia.width and ogImageMedia.height', $layout);
    }

    // The three fallbacks are picked as medias and turned into an url once, so the alt and the dimensions above are read off whichever one won
    public function testTheShareImageFallbackChainKeepsTheMediaItPicked(): void
    {
        $layout = $this->layout();

        $this->assertSame(1, substr_count($layout, "{% set ogImage = absolute_url(asset('/' ~ ogImageMedia.filename))"), 'the url is written once for the whole chain');
        $this->assertLessThan(
            strpos($layout, "site_media('og-image')"),
            strpos($layout, 'urlMetadata.ogImage is not null'),
            'an url describing itself must win over the site-wide default'
        );
    }

    // The summary a template states often comes from a rich-text column (a Page's, a gallery category's), so a raw one would publish escaped markup as the page's description - SiteBundle's layout has always reduced it, and the two being interchangeable this one has to as well
    public function testTheSummaryIsReducedToPlainTextInTheMetas(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('<meta name="description" content="{{ summarySocialNetwork|plain_text }}">', $layout);
        $this->assertStringContainsString('<meta property="og:description" content="{{ summarySocialNetwork|plain_text|slice(0, 150) }}">', $layout);
    }

    // A GDPR banner is not something a shop-only site may go without - the component carries its own enabled/disabled guard, so the layout only has to render it
    public function testTheCookieBannerIsRendered(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Cookie:Consent />', $this->layout());
        $this->assertStringNotContainsString("config('site-enable-cookie-consent')", $this->layout(), 'the guard belongs to the component, not here');
    }

    // Audience measurement is no more tied to having pages than the banner above is - the component carries its own guard too, so the layout only has to render it
    public function testTheMatomoSnippetIsRendered(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Analytics:Matomo />', $this->layout());
        // Read once and once only, by the preconnect below: the snippet's own guard belongs to the component, not to whoever renders it
        $this->assertSame(1, substr_count($this->layout(), "config('site-enable-matomo')"), 'the guard belongs to the component, not here');
    }

    // The snippet is fetched from a third-party host, so without this the DNS lookup and the TLS handshake only start once that JS runs. Never for an instance served by this very host, where the connection is already open, and never for a site that turned the tracking off with its instance url left filled - the connection would be opened to a host the page never sends a measure to
    public function testTheMatomoOriginIsPreconnected(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('<link rel="preconnect" href="{{ matomoOrigin }}">', $layout);
        $this->assertStringContainsString('matomoOrigin != app.request.getSchemeAndHttpHost()', $layout);
        $this->assertStringContainsString("{% set matomoOrigin = config('site-enable-matomo') and config('site-matomo-url') ?", $layout, 'the preconnect must follow the very switch that decides whether the snippet is rendered');
        $this->assertLessThan(
            strpos($layout, 'bundle_stylesheets()'),
            strpos($layout, 'matomoOrigin'),
            'a preconnect emitted after the stylesheets has nothing left to save'
        );
    }

    // The @font-face rules live inside the compiled stylesheets, so the preloads have to come before them
    public function testTheFontsArePreloadedBeforeTheStylesheets(): void
    {
        $layout = $this->layout();

        $this->assertLessThan(
            strpos($layout, 'bundle_stylesheets()'),
            strpos($layout, 'font_preloads()'),
            'font preloads must be emitted before the stylesheets carrying the @font-face rules'
        );
    }

    // Same default as SiteBundle's own layout: "status_code" is only ever set by Symfony's error renderer, and an error page rendered here would otherwise invite search engines to index it
    public function testAnErrorPageIsNotIndexable(): void
    {
        $layout = $this->layout();

        $this->assertSame(
            1,
            preg_match('/<meta name="robots" content="(\{\{.*?\}\})">/', $layout, $matches),
            'The robots meta tag is no longer written as a single expression, this test can no longer read it.'
        );

        $twig = new Environment(new ArrayLoader(['robots' => $matches[1]]));

        $this->assertSame('index, follow', $twig->render('robots', []));
        $this->assertSame('noindex, follow', $twig->render('robots', ['status_code' => 404]));
    }

    // A nonce present on a directive makes 'unsafe-inline' a no-op for it, so style-src has to be nonced on every page or not at all: nonced only where an inline <style> is rendered, it would drop a site's own inline styles on those pages alone and leave them working everywhere else
    public function testStyleSrcIsNoncedOnEveryPage(): void
    {
        $layout = $this->layout();

        $this->assertMatchesRegularExpression("/\{% set \w+ = csp_nonce\('style'\) %\}/", $layout, 'The layout no longer nonces style-src, so it is nonced only on the pages rendering an inline style element.');
        $this->assertLessThan(strpos($layout, '</head>'), strpos($layout, "csp_nonce('style')"), 'The style nonce is called after the head, where the stylesheets it governs have already been linked.');
    }

    // csp_nonce() is NelmioSecurityBundle's own Twig function: with that bundle merely suggested, an app running Config+Ui alone - the exact audience for this layout - got "Unknown csp_nonce function" on every single page. Moving it back out of "require" is what this locks
    public function testTheCspNonceProviderIsARealDependency(): void
    {
        $this->assertStringContainsString('csp_nonce(', $this->layout(), 'this test no longer guards anything');

        // The package root, three levels up: this bundle ships inside c975l/core-bundle and no longer has a composer.json of its own
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 3) . '/composer.json'), true);

        $this->assertArrayHasKey('nelmio/security-bundle', $composer['require']);
    }
}
