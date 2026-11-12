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

// assets/js/consent.js: vanilla-cookieconsent's contract by default, and a banner of the site's own taking over by window.c975lConsentAdapter
#[Group('browser')]
class ConsentAdapterBehaviourTest extends JsCase
{
    // A page with no banner still asks: the accord given in place is kept for the visit and told to whoever listened
    public function testAPageWithNoBannerKeepsTheAccordForTheVisit(): void
    {
        $state = $this->observe(
            '<div></div>',
            [],
            'const consent = mod.consent;
             delete window.CookieConsent;
             sessionStorage.removeItem("c975l-consent-content");
             let heard = 0;
             const stop = consent.onChange(() => { heard += 1; });
             const before = { banner: consent.hasBanner(), ready: consent.isReady(), accepted: consent.isAccepted("content") };
             consent.accept("content");
             stop();
             const after = { accepted: consent.isAccepted("content"), kept: sessionStorage.getItem("c975l-consent-content"), heard };
             sessionStorage.removeItem("c975l-consent-content");

             return { before, after };',
            ['modules' => ['consent' => 'consent']],
        );

        $this->assertSame(['banner' => false, 'ready' => true, 'accepted' => false], $state['before'], 'A page with no banner reads as already accepted, so a third party loads unasked.');
        $this->assertSame(['accepted' => true, 'kept' => '1', 'heard' => 1], $state['after']);
    }

    // A site's own banner answers for itself, its events reaching whoever listened
    public function testABannerOfTheSiteOwnIsAskedInstead(): void
    {
        $state = $this->observe(
            '<div></div>',
            [],
            'const consent = mod.consent;
             let given = false;
             let heard = 0;
             window.c975lConsentAdapter = {
                 present: () => true,
                 accepted: () => given,
                 accept: () => { given = true; window.dispatchEvent(new Event("own:change")); },
                 events: ["own:change"],
             };
             const stop = consent.onChange(() => { heard += 1; });
             const before = consent.isAccepted("content");
             consent.accept("content");
             stop();
             window.dispatchEvent(new Event("own:change"));
             delete window.c975lConsentAdapter;

             return { banner: consent.hasBanner(), before, after: given, heard };',
            ['modules' => ['consent' => 'consent']],
        );

        $this->assertSame(['banner' => false, 'before' => false, 'after' => true, 'heard' => 1], $state);
    }

    // With no banner script loaded yet, nothing is accepted and nothing throws
    public function testVanillaCookieConsentIsTheDefault(): void
    {
        $state = $this->observe(
            '<div data-controller="cookieConsent"></div>',
            [],
            'const consent = mod.consent;
             delete window.CookieConsent;
             const early = { banner: consent.hasBanner(), ready: consent.isReady(), accepted: consent.isAccepted("content") };
             consent.accept("content");
             window.CookieConsent = { acceptedCategory: (category) => "content" === category };

             return { ...early, later: consent.isAccepted("content") };',
            ['modules' => ['consent' => 'consent']],
        );

        $this->assertSame(['banner' => true, 'ready' => false, 'accepted' => false, 'later' => true], $state);
    }

    // Accepting one category keeps the ones the visitor had already accepted in the banner, vanilla-cookieconsent replacing the set with whatever it is given
    public function testAcceptingACategoryKeepsTheOthersAccepted(): void
    {
        $state = $this->observe(
            '<div data-controller="cookie-consent"></div>',
            [],
            'const consent = mod.consent;
             let given = null;
             window.CookieConsent = {
                 acceptedCategory: () => false,
                 getUserPreferences: () => ({ acceptedCategories: ["necessary", "analytics"] }),
                 acceptCategory: (categories) => { given = categories; },
             };
             consent.accept("content");
             delete window.CookieConsent;

             return given;',
            ['modules' => ['consent' => 'consent']],
        );

        $this->assertSame(['necessary', 'analytics', 'content'], $state, 'A map or a video accepted withdraws the analytics the visitor had agreed to.');
    }
}
