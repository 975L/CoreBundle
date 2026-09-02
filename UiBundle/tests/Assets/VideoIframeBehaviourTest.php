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

// assets/js/video-iframe.js run against the markup templates/components/Video/Iframe.html.twig renders, with a consent banner answering as vanilla-cookieconsent does
// Of everything this bundle ships this is the controller whose failures are worst and quietest: the iframe pulls about a megabyte of a platform's javascript and sets that platform's cookies, and every way of getting it wrong renders a page that looks entirely correct. A test reading the file as text cannot tell a guard that runs from a guard that was moved below the line it guards
#[Group('browser')]
class VideoIframeBehaviourTest extends JsCase
{
    // An address that refuses the connection rather than a platform's real one: three of these scenarios do frame the player, and a live iframe with a remote src navigates its subframe for real
    private const string SRC = 'https://127.0.0.1:1/embed/xyz';

    // The banner element video-iframe.js looks for, as c975l/ui-bundle's own component writes it
    private const string BANNER = '<div data-controller="cookie-consent"></div>';

    // A consent library standing where vanilla-cookieconsent stands, answering the two questions the controller asks it. The real one is exercised by CookieConsentContractTest, which is what says these two names are still the right ones
    private const string CONSENT = 'window.CookieConsent = {
        granted: %s,
        acceptedCategory(category) { return "content" === category && this.granted; },
        acceptCategory(category) { this.granted = "content" === category; window.dispatchEvent(new CustomEvent("cc:onChange")); },
    };';

    // No banner at all on the page: a site that uses none must not have its content held back for an answer nobody will ever be asked for
    public function testASiteWithoutABannerGetsItsPlayerWithoutBeingAskedAnything(): void
    {
        $this->assertSame(self::SRC, $this->framed($this->markup(), ['before' => 'delete window.CookieConsent;', 'settle' => 250]), 'A site with no consent banner never gets its video framed at all.');
    }

    // The failure this whole arrangement exists to prevent
    public function testAPlayerIsNeverFramedWhileConsentIsStillPending(): void
    {
        $this->assertNull(
            $this->framed(self::BANNER . $this->markup(), ['before' => sprintf(self::CONSENT, 'false'), 'settle' => 250]),
            'The player was framed with consent still pending: a third-party iframe, its megabyte of javascript and its cookies, all before the visitor answered.'
        );
    }

    // Whoever provides the banner chooses its Stimulus identifier, and a spelling this controller does not match reads as "no banner on this page" - which loads the player straight away
    public function testABannerRegisteredUnderTheOtherSpellingIsFoundJustTheSame(): void
    {
        $this->assertNull(
            $this->framed('<div data-controller="cookieConsent"></div>' . $this->markup(), ['before' => sprintf(self::CONSENT, 'false'), 'settle' => 250]),
            'A banner registered under the camelCase spelling is not recognised, so the player loads before the visitor has answered.'
        );
    }

    // The player is about a megabyte of a platform's javascript: a visitor who never scrolls down to it must never pay for it
    public function testAPlayerFarBelowTheFoldIsOnlyFramedOnceItIsApproached(): void
    {
        $state = $this->observe(
            '<div style="height: 4000px"></div>' . $this->markup(),
            ['videoIframe' => 'video-iframe'],
            'const before = !!root.querySelector("iframe");
             root.querySelector("[data-videoiframe-src-value]").scrollIntoView();
             await new Promise((r) => setTimeout(r, 200));

             return { before, after: !!root.querySelector("iframe") };',
            ['before' => 'delete window.CookieConsent;', 'settle' => 250]
        );

        $this->assertFalse($state['before'], 'A player four thousand pixels below the fold was framed on load, which is the megabyte this deferral exists to save.');
        $this->assertTrue($state['after'], 'The player was never framed even once it was scrolled to.');
    }

    // A refusal that reaches the controller only through the event, the banner's script having loaded after the page did
    public function testARefusalAnnouncedAfterTheFactStillLeavesThePlayerUnframed(): void
    {
        $this->assertNull(
            $this->framed(
                self::BANNER . $this->markup(),
                [
                    'before' => sprintf(self::CONSENT, 'false'),
                    'settle' => 150,
                    'probe' => 'window.dispatchEvent(new CustomEvent("cc:onConsent"));
                                await new Promise((r) => setTimeout(r, 150));',
                ]
            ),
            'A refusal announced by the banner framed the player anyway.'
        );
    }

    // A returning visitor whose answer is already known when the page loads: "cc:onConsent" fires on every load, not only the first
    public function testAVisitorWhoAlreadyAcceptedGetsThePlayerWithoutClickingAgain(): void
    {
        $this->assertSame(
            self::SRC,
            $this->framed(self::BANNER . $this->markup(), ['before' => sprintf(self::CONSENT, 'true'), 'settle' => 250]),
            'A visitor who had already accepted is asked again, so the answer stored by the banner buys them nothing.'
        );
    }

    // With a poster there is something to look at, so the player waits for a click rather than pulling a megabyte per thumbnail in a grid of them
    public function testWithAPosterConsentRevealsAPlayButtonRatherThanFramingThePlayer(): void
    {
        $state = $this->observe(
            self::BANNER . $this->markup(true),
            ['videoIframe' => 'video-iframe'],
            'return {
                 framed: !!root.querySelector("iframe"),
                 play: !root.querySelector("[data-videoiframe-target=play]").hidden,
                 prompt: !root.querySelector("[data-videoiframe-target=consent]").hidden,
             };',
            ['before' => sprintf(self::CONSENT, 'true'), 'settle' => 250]
        );

        $this->assertFalse($state['framed'], 'The player was framed on sight, so a page of six posters pulls six megabytes of third-party javascript.');
        $this->assertTrue($state['play'], 'Consent was given and no play button was revealed, leaving a poster nothing can be done with.');
        $this->assertFalse($state['prompt'], 'The consent prompt is still asking for an answer that was already given.');
    }

    // Clicking play is what frames the player, once and with the attributes a platform needs
    public function testClickingPlayFramesThePlayerWithTheAttributesAPlatformNeeds(): void
    {
        $iframe = $this->observe(
            self::BANNER . $this->markup(true),
            ['videoIframe' => 'video-iframe'],
            'root.querySelector("[data-videoiframe-target=play]").click();
             await new Promise((r) => setTimeout(r, 150));
             const iframe = root.querySelector("iframe");

             return iframe ? {
                 src: iframe.getAttribute("src"),
                 title: iframe.getAttribute("title"),
                 allow: iframe.getAttribute("allow"),
                 referrer: iframe.getAttribute("referrerpolicy"),
                 loading: iframe.getAttribute("loading"),
                 fullscreen: iframe.hasAttribute("allowfullscreen"),
                 autoplay: iframe.getAttribute("src").includes("autoplay") || iframe.getAttribute("allow").includes("autoplay"),
             } : null;',
            ['before' => sprintf(self::CONSENT, 'true'), 'settle' => 250]
        );

        $this->assertNotNull($iframe, 'Clicking play framed nothing at all.');
        $this->assertSame(self::SRC, $iframe['src']);
        $this->assertSame('Une video', $iframe['title'], 'The player carries no title, so a screen reader announces an unnamed frame.');
        $this->assertStringContainsString('encrypted-media', (string) $iframe['allow'], 'The permissions a player needs are no longer granted, so a protected video refuses to play.');
        $this->assertSame('strict-origin-when-cross-origin', $iframe['referrer'], 'Without a referrer policy of its own the frame sends whatever the page declares, and a platform checking the domain refuses to serve the video.');
        $this->assertSame('lazy', $iframe['loading']);
        $this->assertTrue($iframe['fullscreen']);
        $this->assertFalse($iframe['autoplay'], 'The player is allowed to start on its own - autoplay is granted by the framing page, and a returning visitor merely scrolling past would be played at.');
    }

    // The banner's answer can be withdrawn at any moment, and a button left on screen goes on offering a video that has just been refused
    public function testWithdrawingConsentPutsThePromptBackOverTheRevealedButton(): void
    {
        $state = $this->observe(
            self::BANNER . $this->markup(true),
            ['videoIframe' => 'video-iframe'],
            'const revealed = !root.querySelector("[data-videoiframe-target=play]").hidden;
             window.CookieConsent.acceptCategory([]);
             await new Promise((r) => setTimeout(r, 150));

             return {
                 revealed,
                 play: !root.querySelector("[data-videoiframe-target=play]").hidden,
                 prompt: !root.querySelector("[data-videoiframe-target=consent]").hidden,
             };',
            ['before' => sprintf(self::CONSENT, 'true'), 'settle' => 250]
        );

        $this->assertTrue($state['revealed'], 'The button was never revealed, so the withdrawal being tested proves nothing.');
        $this->assertFalse($state['play'], 'A withdrawn consent leaves the play button up, offering a video the visitor just refused.');
        $this->assertTrue($state['prompt'], 'The consent prompt never comes back, so there is no way to answer again.');
    }

    // A withdrawal the banner never announced - its script loaded late, the event was missed - leaves a button on screen with nothing behind it, so the click is checked against the answer of the moment
    public function testAClickIsCheckedAgainstTheAnswerOfTheMomentRatherThanTheOneThatRevealedIt(): void
    {
        $state = $this->observe(
            self::BANNER . $this->markup(true),
            ['videoIframe' => 'video-iframe'],
            'window.CookieConsent.granted = false;
             root.querySelector("[data-videoiframe-target=play]").click();
             await new Promise((r) => setTimeout(r, 150));

             return {
                 framed: !!root.querySelector("iframe"),
                 prompt: !root.querySelector("[data-videoiframe-target=consent]").hidden,
             };',
            ['before' => sprintf(self::CONSENT, 'true'), 'settle' => 250]
        );

        $this->assertFalse($state['framed'], 'A click framed the player although consent had been withdrawn behind the banner in the meantime.');
        $this->assertTrue($state['prompt'], 'The prompt was not put back, leaving the visitor no way to answer.');
    }

    // An empty src is not "no iframe": the browser resolves it on the document's own url and the page loads inside itself
    public function testAnEmptySourceFramesNothingAtAll(): void
    {
        $this->assertFalse(
            (bool) $this->observe(
                str_replace(self::SRC, '', $this->markup()),
                ['videoIframe' => 'video-iframe'],
                'return !!root.querySelector("iframe");',
                ['before' => 'delete window.CookieConsent;', 'settle' => 250]
            ),
            'A component with no source framed something anyway, which is the page loading inside itself.'
        );
    }

    // Turbo caches the page as it stands, so a snapshot restored later would put a revealed button back on screen before consent has been checked again
    public function testDisconnectingPutsTheButtonBackBehindItsPromptForTheSnapshot(): void
    {
        $state = $this->observe(
            self::BANNER . $this->markup(true),
            ['videoIframe' => 'video-iframe'],
            'const figure = root.querySelector("[data-videoiframe-src-value]");
             // Detached rather than stripped of its data-controller: taking the attribute off invalidates the scope before disconnect() is called, so the controller would find no target to put back - which is not what a page swap does to it
             document.createElement("div").appendChild(figure);
             await new Promise((r) => setTimeout(r, 150));

             return {
                 play: !figure.querySelector("[data-videoiframe-target=play]").hidden,
                 prompt: !figure.querySelector("[data-videoiframe-target=consent]").hidden,
             };',
            ['before' => sprintf(self::CONSENT, 'true'), 'settle' => 250]
        );

        $this->assertFalse($state['play'], 'The revealed button is frozen into the cached snapshot, so it comes back before consent is checked again.');
        $this->assertTrue($state['prompt'], 'The prompt is not restored in the snapshot.');
    }

    // The size is carried by the whole component and not by the iframe alone: cover and player follow each other in one box, or the grey box is as wide as the card and the player shrinks the moment consent is given
    public function testTheRequestedSizeBoundsTheWholeComponentAndScalesDownInANarrowColumn(): void
    {
        $sizing = $this->observe(
            $this->markup(false, ' data-videoiframe-width-value="640"'),
            ['videoIframe' => 'video-iframe'],
            'const figure = root.querySelector("[data-videoiframe-src-value]");
             const rule = [...document.querySelectorAll("style")].map((s) => s.textContent).join("");

             return { max: getComputedStyle(figure).maxWidth, ratio: rule.includes("aspect-ratio: 640 / 360"), scoped: rule.includes(figure.id) };',
            ['before' => 'delete window.CookieConsent;', 'settle' => 250]
        );

        $this->assertSame('640px', $sizing['max'], 'The requested width does not bound the component, so the consent cover is as wide as the page.');
        $this->assertTrue($sizing['ratio'], 'The height was not derived from the width on 16/9, so only one of the two boxes is sized.');
        $this->assertTrue($sizing['scoped'], 'The sizing rule is not scoped to this component, so it reaches every other player on the page.');
    }

    private function markup(bool $poster = false, string $extra = ''): string
    {
        return sprintf(
            '<div class="img img-responsive video-iframe-consent" data-controller="videoIframe" data-videoiframe-src-value="%s" data-videoiframe-title-value="Une video"%s>
                <div data-videoiframe-target="placeholder" class="video-iframe-placeholder">
                    %s
                    <div data-videoiframe-target="consent" class="video-iframe-prompt">
                        <p>Consentement requis</p>
                        <button type="button" data-action="videoIframe#accept">Accepter</button>
                    </div>
                    %s
                </div>
            </div>',
            self::SRC,
            $extra,
            $poster ? '<img class="video-iframe-poster" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" aria-hidden="true">' : '',
            $poster ? '<button type="button" class="video-iframe-play" data-videoiframe-target="play" data-action="videoIframe#play" hidden><span>Lire</span></button>' : ''
        );
    }

    // The src of the framed player, or null when nothing was framed at all
    private function framed(string $html, array $options): ?string
    {
        $probe = $options['probe'] ?? '';
        unset($options['probe']);

        return $this->observe(
            $html,
            ['videoIframe' => 'video-iframe'],
            $probe . 'return root.querySelector("iframe")?.getAttribute("src") ?? null;',
            $options
        );
    }
}
