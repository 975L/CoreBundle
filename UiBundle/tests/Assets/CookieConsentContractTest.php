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

// The vendored vanilla-cookieconsent actually run, through the exact configuration assets/js/cookie-consent.js hands it
// This is the one library here whose contract is not merely a feature: video-iframe.js asks it whether the "content" category is accepted before framing a player, and a rename on either side stops the question being asked at all - the iframe then loads for a visitor who refused, which is the failure this whole arrangement exists to prevent
#[Group('browser')]
class CookieConsentContractTest extends JsCase
{
    private const string LIBRARY = 'public/js/cookieconsent.umd.js';

    private const string STYLESHEET = 'public/css/cookieconsent.css';

    // The single non-essential category this bundle declares, as cookie-consent.js writes it
    private const string CONFIG = '{
        categories: { necessary: { enabled: true, readOnly: true }, content: {} },
        guiOptions: { consentModal: { layout: "bar inline", position: "bottom" } },
        language: { default: "fr", translations: { fr: { consentModal: {
            label: "Gestion des cookies",
            description: "Ce site depose des cookies.",
            acceptAllBtn: "Accepter",
            acceptNecessaryBtn: "Refuser",
        } } } },
    }';

    public function testTheVendoredLibraryStillDefinesTheGlobalTheControllerWaitsFor(): void
    {
        $this->assertSame('object', $this->banner('return typeof window.CookieConsent;'));
    }

    // The banner is a role="dialog" with no title in the "bar inline" layout, so its aria-label is the only accessible name it has - without one a screen reader announces an unnamed dialog over the whole page
    public function testTheBannerIsADialogNamedByTheLabelThisBundlePasses(): void
    {
        $banner = $this->banner(
            'const dialog = document.querySelector("#cc-main [role=dialog]");

             return {
                 named: dialog?.getAttribute("aria-label") ?? null,
                 layout: dialog?.className ?? "",
                 buttons: [...document.querySelectorAll("#cc-main button")].map((b) => b.textContent.trim()),
             };'
        );

        $this->assertSame('Gestion des cookies', $banner['named'], 'The banner no longer takes its accessible name from the "label" this bundle passes.');
        $this->assertStringContainsString('cm--bar', $banner['layout'], 'The "bar inline" layout this bundle asks for is no longer applied.');
        $this->assertContains('Accepter', $banner['buttons']);
        $this->assertContains('Refuser', $banner['buttons'], 'The reject button is gone, which would leave a banner offering acceptance only.');
    }

    // The whole binary choice this bundle offers: one non-essential category, refused until it is accepted
    public function testAcceptingTheContentCategoryIsWhatTurnsTheAnswerAround(): void
    {
        $answers = $this->banner(
            'const before = CookieConsent.acceptedCategory("content");
             CookieConsent.acceptCategory("content");
             await new Promise((r) => setTimeout(r, 50));

             return { before, after: CookieConsent.acceptedCategory("content") };'
        );

        $this->assertFalse($answers['before'], 'A category is accepted before the visitor has answered, so an embed would load unasked.');
        $this->assertTrue($answers['after'], '"acceptCategory" no longer makes "acceptedCategory" answer true, which is the pair video-iframe.js reads.');
    }

    // "cc:onConsent" fires on every load once the answer is known, not only the first time, which is what lets a returning visitor get their embeds back without clicking again
    public function testTheEventsVideoIframeListensForAreStillDispatched(): void
    {
        $seen = $this->banner(
            'const seen = [];
             const record = (event) => seen.push(event.type);
             window.addEventListener("cc:onConsent", record);
             window.addEventListener("cc:onChange", record);
             __start__
             CookieConsent.acceptCategory("content");
             await new Promise((r) => setTimeout(r, 100));
             CookieConsent.acceptCategory([]);
             await new Promise((r) => setTimeout(r, 100));
             window.removeEventListener("cc:onConsent", record);
             window.removeEventListener("cc:onChange", record);

             return seen;'
        );

        $this->assertContains('cc:onConsent', $seen, '"cc:onConsent" is no longer dispatched, so a returning visitor never gets the embeds they already accepted.');
        $this->assertContains('cc:onChange', $seen, '"cc:onChange" is no longer dispatched, so a withdrawn consent leaves the embeds on screen.');
    }

    // Withdrawing is the half that is easy to leave untested and the one the law cares about
    public function testWithdrawingTheCategoryTakesTheAnswerBack(): void
    {
        $this->assertFalse(
            $this->banner(
                'CookieConsent.acceptCategory("content");
                 await new Promise((r) => setTimeout(r, 50));
                 CookieConsent.acceptCategory([]);
                 await new Promise((r) => setTimeout(r, 50));

                 return CookieConsent.acceptedCategory("content");'
            ),
            'A withdrawn category still answers accepted, so nothing would ever take a third-party embed back off the page.'
        );
    }

    // The essential category is declared read-only, and a library letting it be switched off would be answering for a choice this bundle never offers
    public function testTheNecessaryCategoryCannotBeRefused(): void
    {
        $this->assertTrue(
            $this->banner(
                'CookieConsent.acceptCategory([]);
                 await new Promise((r) => setTimeout(r, 50));

                 return CookieConsent.acceptedCategory("necessary");'
            ),
            'The read-only category was refused, which is not a choice this bundle puts to the visitor.'
        );
    }

    // The answer is kept in a cookie, which is why these scenarios are served over http at all: on a file page the library accepts and remembers nothing
    public function testTheAnswerIsStoredWhereAReturningVisitorIsReadFrom(): void
    {
        $this->assertStringContainsString(
            'cc_cookie',
            (string) $this->banner(
                'CookieConsent.acceptCategory("content");
                 await new Promise((r) => setTimeout(r, 50));

                 return document.cookie;'
            ),
            'The answer is stored nowhere, so every page load asks the visitor again.'
        );
    }

    // A scenario normally starts the banner up front; one that has to be listening before the first event is dispatched places "__start__" itself instead
    private function banner(string $probe): mixed
    {
        // A singleton on window, so the previous scenario's answer is still in it - the cookie is cleared by the harness, the instance has to be told
        $reset = 'window.CookieConsent?.reset?.(true);';
        // Waited on until the banner is not merely there but built: the library generates its buttons lazily
        $start = sprintf(
            'await CookieConsent.run(%s);
             for (let attempt = 0; attempt < 150 && !(document.querySelector("#cc-main [data-role=all]") && document.querySelector("#cc-main [role=dialog]")?.getAttribute("aria-label")); attempt += 1) {
                 await new Promise((r) => setTimeout(r, 20));
             }',
            self::CONFIG
        );

        $probe = str_contains($probe, '__start__')
            ? str_replace('__start__', $start, $probe)
            : $start . $probe;

        return $this->observe('<div></div>', [], $reset . $probe, ['scripts' => [self::LIBRARY], 'styles' => [self::STYLESHEET]]);
    }
}
