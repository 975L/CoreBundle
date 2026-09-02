<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/cookie-consent.js driven end to end: the values templates/components/Cookie/Consent.html.twig writes on the element, the vendored library fetched from this bundle's own public/, and the banner it puts on screen
// This controller is the one place a visitor is asked anything at all, and nearly everything that can go wrong with it is silent - a value never read leaves a banner with an empty button, a script url pointing nowhere leaves no banner at all, and the page looks perfectly fine either way
#[Group('browser')]
class CookieConsentBehaviourTest extends JsCase
{
    // The library is fetched by the controller itself, from the urls the template hands it: the whole point is that no CDN is involved
    private function banner(string $probe, array $values = []): mixed
    {
        $values = array_merge([
            'stylesheet' => $this->url('public/css/cookieconsent.css'),
            'script' => $this->url('public/js/cookieconsent.umd.js'),
            'message' => 'Ce site depose des cookies.',
            'label' => 'Gestion des cookies',
            'accept' => 'Accepter',
            'reject' => 'Refuser',
            'lang' => 'fr',
        ], $values);

        $attributes = '';
        foreach ($values as $name => $value) {
            if (null === $value) {
                continue;
            }

            // Kebab-case on both sides, as the template writes them: Stimulus dasherizes the value name but not the identifier
            $attributes .= sprintf(' data-cookie-consent-%s-value="%s"', $this->dasherize($name), htmlspecialchars((string) $value, \ENT_QUOTES));
        }

        return $this->observe(
            sprintf('<div data-controller="cookie-consent"%s></div>', $attributes),
            ['cookie-consent' => 'cookie-consent'],
            '// Waited on until the banner is not merely there but built: the library generates its buttons lazily, so a dialog element on screen is not yet a dialog anything can be clicked in
             for (let attempt = 0; attempt < 150 && !(document.querySelector("#cc-main [data-role=all]") && document.querySelector("#cc-main [role=dialog]")?.getAttribute("aria-label")); attempt += 1) {
                 await new Promise((r) => setTimeout(r, 20));
             }
             ' . $probe,
            // The library is a singleton on window, so the previous scenario's answer outlives its banner
            [
                'before' => 'window.CookieConsent?.reset?.(true); window.__head = new Set(document.head.children);',
                'settle' => 500,
            ]
        );
    }

    // Nothing on the element is a hard-coded string: a value the controller stops reading leaves the banner up with an empty button, which no test reading the file as text would notice
    public function testTheBannerIsBuiltFromTheValuesTheTemplateWrites(): void
    {
        $banner = $this->banner(
            'const dialog = document.querySelector("#cc-main [role=dialog]");

             return {
                 named: dialog?.getAttribute("aria-label") ?? null,
                 text: dialog?.textContent.replace(/\s+/g, " ").trim() ?? "",
                 buttons: [...document.querySelectorAll("#cc-main button")].map((b) => b.textContent.trim()),
             };'
        );

        $this->assertSame('Gestion des cookies', $banner['named'], 'The dialog takes its accessible name from nowhere, leaving a role="dialog" a screen reader cannot announce.');
        $this->assertStringContainsString('Ce site depose des cookies.', $banner['text'], 'The message value never reaches the banner.');
        $this->assertContains('Accepter', $banner['buttons']);
        $this->assertContains('Refuser', $banner['buttons'], 'The reject button is missing, which leaves a banner offering acceptance only - the one shape consent law does not allow.');
    }

    // The link is conditional in the template, so it is conditional here: a site with no cookies policy configured must not get an empty href
    public function testThePolicyLinkIsOnlyThereWhenAPolicyUrlIsGiven(): void
    {
        $withUrl = $this->banner(
            'const link = document.querySelector("#cc-main a");

             return { href: link?.getAttribute("href") ?? null, text: link?.textContent.trim() ?? null, rel: link?.getAttribute("rel") ?? null };',
            ['policy-url' => 'https://example.test/cookies', 'policy-label' => 'Notre politique']
        );

        $this->assertSame('https://example.test/cookies', $withUrl['href']);
        $this->assertSame('Notre politique', $withUrl['text']);
        $this->assertStringContainsString('noopener', (string) $withUrl['rel'], 'The policy link opens in a new tab without "noopener", handing the opened page a handle on this one.');

        $this->assertNull(
            $this->banner('return document.querySelector("#cc-main a")?.getAttribute("href") ?? null;')['href'] ?? null,
            'A site with no cookies policy still gets a link in its banner.'
        );
    }

    // The single non-essential category this bundle declares, and the pair video-iframe.js reads it through
    public function testTheContentCategoryStartsRefusedAndIsWhatTheAcceptButtonGrants(): void
    {
        $answers = $this->banner(
            'const before = window.CookieConsent.acceptedCategory("content");
             document.querySelector("#cc-main [data-role=all]").click();
             await new Promise((r) => setTimeout(r, 150));

             return { before, after: window.CookieConsent.acceptedCategory("content") };'
        );

        $this->assertFalse($answers['before'], 'The category is granted before the visitor has answered, so every embed on the site loads unasked.');
        $this->assertTrue($answers['after'], 'Accepting from the banner grants nothing, so a visitor who agreed still gets no embeds.');
    }

    // Refusing has to be a real answer and not merely a dismissal, or the banner comes back at every page and the embeds load anyway
    public function testRefusingLeavesTheContentCategoryUngrantedAndClosesTheBanner(): void
    {
        $after = $this->banner(
            'document.querySelector("#cc-main [data-role=necessary]").click();
             // Waited on rather than slept through: the library stores the answer once its banner has finished going away, and how long that takes is the machine\'s business
             for (let attempt = 0; attempt < 100 && !document.cookie.includes("cc_cookie"); attempt += 1) {
                 await new Promise((r) => setTimeout(r, 20));
             }

             return {
                 granted: window.CookieConsent.acceptedCategory("content"),
                 visible: !!document.querySelector("#cc-main [role=dialog]")?.offsetParent,
                 stored: document.cookie.includes("cc_cookie"),
             };'
        );

        $this->assertFalse($after['granted'], 'Refusing granted the category anyway.');
        $this->assertFalse($after['visible'], 'The banner stays on screen after an answer, so the visitor is asked again over the content they came for.');
        $this->assertTrue($after['stored'], 'The refusal is stored nowhere, so the banner asks again on the next page.');
    }

    // The locale the template passes decides which translation the library shows - a controller reading it wrong falls back to a language the visitor never asked for
    public function testTheBannerIsShownInTheLocaleTheTemplatePasses(): void
    {
        $this->assertStringContainsString(
            'Manage cookies',
            (string) $this->banner(
                'return document.querySelector("#cc-main [role=dialog]")?.getAttribute("aria-label") ?? "";',
                ['lang' => 'en', 'label' => 'Manage cookies']
            ),
            'The banner ignores the locale it is handed.'
        );
    }

    // Served from this bundle's own public/, never from a CDN: an external host would receive every visitor's IP before any consent is given, which is the thing the banner is there to ask about
    public function testNothingIsFetchedFromAnywhereButThisBundle(): void
    {
        // Only what this scenario put in the head: the page is shared by the whole run, and every other scenario has left its own loads in there
        $sources = $this->banner(
            'const added = (selector, attribute) => [...document.querySelectorAll(selector)].filter((el) => !window.__head.has(el)).map((el) => el[attribute]);

             return { scripts: added("script[src]", "src"), styles: added("link[rel=stylesheet]", "href") };'
        );

        $this->assertNotSame([], array_merge($sources['scripts'], $sources['styles']), 'The banner loaded nothing at all, so this proves nothing about where it loads from.');

        foreach (array_merge($sources['scripts'], $sources['styles']) as $source) {
            $this->assertStringStartsWith('http://127.0.0.1:', (string) $source, sprintf('"%s" is fetched from somewhere other than the site itself.', $source));
        }
    }

    private function dasherize(string $name): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
    }
}
