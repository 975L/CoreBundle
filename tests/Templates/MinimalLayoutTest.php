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

    // A GDPR banner is not something a shop-only site may go without - the component carries its own enabled/disabled guard, so the layout only has to render it
    public function testTheCookieBannerIsRendered(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Cookie:Consent />', $this->layout());
        $this->assertStringNotContainsString("config('site-enable-cookie-consent')", $this->layout(), 'the guard belongs to the component, not here');
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

    // csp_nonce() is NelmioSecurityBundle's own Twig function: with that bundle merely suggested, an app running Config+Ui alone - the exact audience for this layout - got "Unknown csp_nonce function" on every single page. Moving it back out of "require" is what this locks
    public function testTheCspNonceProviderIsARealDependency(): void
    {
        $this->assertStringContainsString('csp_nonce(', $this->layout(), 'this test no longer guards anything');

        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/composer.json'), true);

        $this->assertArrayHasKey('nelmio/security-bundle', $composer['require']);
    }
}
