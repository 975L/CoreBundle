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

// assets/js/rating.js clicked, with the vote route answered by the scenario itself and a real localStorage underneath
// The widget holds three things at once - what this browser voted, what everyone else did, and whether a request is in flight - and the ways they come apart are all silent: a second click sent while the first is still out breaks on the unique index the voter is held by, and a rate-limited visitor is told "could not be recorded" and clicks the same icon forever
#[Group('browser')]
class RatingBehaviourTest extends JsCase
{
    private const string SERVER = 'window.__posts = [];
        window.__answer = { value: 4, count: 11, average: 4.2 };
        window.__status = 200;
        window.fetch = (url, options) => {
            window.__posts.push({ url: String(url), options, body: JSON.parse(options.body) });

            return window.__status === 200
                ? Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(window.__answer) })
                : Promise.resolve({ ok: false, status: window.__status });
        };';

    // Nothing is written to this browser before a click, which is what keeps the widget out of consent territory
    public function testNoIdentifierIsCreatedForSomebodyWhoOnlyReadsThePage(): void
    {
        $stored = $this->rating('return { keys: Object.keys(window.localStorage), stars: root.querySelectorAll(".rating-star--on").length };');

        $this->assertSame([], $stored['keys'], 'An identifier is minted for a visitor who has done nothing but load the page.');
        $this->assertSame(0, $stored['stars'], 'Stars are painted before anything was read or voted.');
    }

    // The token is minted on the click, sent with the vote, and kept for the next one
    public function testTheFirstVoteMintsATokenSendsItAndRemembersIt(): void
    {
        $state = $this->rating(
            'root.querySelectorAll("[data-rating-value-param]")[3].click();
             await tick();
             const stored = JSON.parse(window.localStorage.getItem("c975l-rating"));

             return { body: window.__posts[0].body, credentials: window.__posts[0].options.credentials, type: window.__posts[0].options.headers["Content-Type"], stored, stars: root.querySelectorAll(".rating-star--on").length, voted: root.querySelector("[data-controller]").classList.contains("rating-vote--voted") };'
        );

        $this->assertSame(4, $state['body']['value'], 'The clicked score is not what is sent.');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $state['body']['token'], 'The token sent is not the 32-character shape the server accepts.');
        $this->assertArrayNotHasKey('scale', $state['body'], 'The scale is sent from the browser, where it is the site\'s own setting and the server reads it there.');
        $this->assertSame('application/json', $state['type'], 'The vote is not sent as json, which is what the route reads it as.');
        $this->assertSame('same-origin', $state['credentials'], 'The request is not restricted to this site, so a browser could be made to carry the vote elsewhere.');
        $this->assertSame($state['body']['token'], $state['stored']['token'], 'The token was not kept, so the next vote is cast as a different visitor.');
        $this->assertSame(4, $state['stars'], 'The visitor\'s own vote is not painted back.');
        $this->assertTrue($state['voted']);
    }

    // The server decides: re-sending the score already stored takes the vote back, which is the toggle a single-icon "like" needs
    public function testAVoteTakenBackByTheServerIsForgottenHere(): void
    {
        $state = $this->rating(
            'const stars = root.querySelectorAll("[data-rating-value-param]");
             stars[3].click();
             await tick();
             window.__answer = { value: null, count: 10, average: 4.0 };
             stars[3].click();
             await tick();

             return { stored: JSON.parse(window.localStorage.getItem("c975l-rating")).votes, on: root.querySelectorAll(".rating-star--on").length, voted: root.querySelector("[data-controller]").classList.contains("rating-vote--voted") };'
        );

        $this->assertSame([], $state['stored'], 'A vote the server took back is still stored here, so the widget shows one the server no longer holds.');
        $this->assertFalse($state['voted'], 'The widget still says the visitor has voted.');
        $this->assertSame(4, $state['on'], 'With the visitor\'s own vote gone, the row does not fall back on the public average.');
    }

    // Two clicks in a row would send two votes the server reads as two first votes, and the second insert breaks on the unique index
    public function testASecondClickWhileTheFirstIsStillOutIsIgnored(): void
    {
        $this->assertSame(
            1,
            $this->rating(
                'const stars = root.querySelectorAll("[data-rating-value-param]");
                 stars[3].click();
                 stars[4].click();
                 await tick();

                 return window.__posts.length;'
            ),
            'A second click was sent while the first vote was still out, which the server reads as two first votes from one visitor.'
        );
    }

    // The route is rate limited per address, and a visitor rating a whole catalogue reaches that ceiling in the ordinary course of browsing
    public function testARateLimitedVisitorIsToldToComeBackRatherThanThatItFailed(): void
    {
        $throttled = $this->rating(
            'window.__status = 429;
             root.querySelectorAll("[data-rating-value-param]")[3].click();
             await tick();
             const said = root.querySelector("[data-rating-target=tally]").textContent;
             window.__status = 500;
             root.querySelectorAll("[data-rating-value-param]")[3].click();
             await tick();

             return { said, other: root.querySelector("[data-rating-target=tally]").textContent };'
        );

        $this->assertSame('Revenez dans quelques minutes', $throttled['said'], 'A rate-limited visitor is told the vote failed, so they click the same icon again and again for nothing.');
        $this->assertSame('Vote impossible', $throttled['other'], 'Every failure is reported as a rate limit, including the ones a visitor can do nothing about.');
    }

    // A failure must not leave the widget dead for the rest of the page
    public function testAFailedVoteLeavesTheWidgetUsable(): void
    {
        $this->assertSame(
            2,
            $this->rating(
                'window.__status = 500;
                 root.querySelectorAll("[data-rating-value-param]")[3].click();
                 await tick();
                 window.__status = 200;
                 root.querySelectorAll("[data-rating-value-param]")[3].click();
                 await tick();

                 return window.__posts.length;'
            ),
            'A failed vote left the widget refusing every later click, so the visitor can never vote again on this page.'
        );
    }

    // A single icon is a "like": the average of a column of ones says nothing, and the count says everything
    public function testASingleIconWidgetSaysHowManyRatherThanOutOfHowMuch(): void
    {
        $said = $this->rating(
            'root.querySelectorAll("[data-rating-value-param]")[0].click();
             await tick();

             return root.querySelector("[data-rating-target=tally]").textContent;',
            '',
            ['scale' => 1]
        );

        $this->assertSame('11 avis', $said, 'A one-icon widget states an average out of one, which says nothing at all.');
    }

    // A compact widget keeps the score and drops the sentence, which would take the width of the card it sits in
    public function testACompactWidgetKeepsTheScoreAndDropsTheSentence(): void
    {
        $said = $this->rating(
            'root.querySelectorAll("[data-rating-value-param]")[3].click();
             await tick();

             return root.querySelector("[data-rating-target=tally]").textContent;',
            '',
            ['compact' => 'true']
        );

        $this->assertSame('4.2/5', $said, 'A compact widget still writes the sentence beside the score.');
    }

    // A browser refusing storage votes as a fresh visitor each time rather than throwing, which is the same degradation as a cleared browser
    public function testABrowserRefusingStorageStillVotes(): void
    {
        $this->assertSame(
            1,
            $this->rating(
                'root.querySelectorAll("[data-rating-value-param]")[3].click();
                 await tick();

                 return window.__posts.length;',
                'Object.defineProperty(window, "localStorage", { configurable: true, get() { throw new DOMException("blocked"); } });'
            ),
            'A browser refusing storage cannot vote at all, where it should simply vote as a fresh visitor.'
        );
    }

    // A vote cast on an earlier visit is painted back from this browser's own store, without asking the server anything
    public function testAVoteFromAnEarlierVisitIsPaintedBackWithoutARequest(): void
    {
        $state = $this->rating(
            'return { on: root.querySelectorAll(".rating-star--on").length, pressed: root.querySelectorAll("[aria-pressed=true]").length, posts: window.__posts.length };',
            'window.localStorage.setItem("c975l-rating", JSON.stringify({ token: "a".repeat(32), votes: { "book-1": 2 } }));'
        );

        $this->assertSame(2, $state['on'], 'A vote cast on an earlier visit is not painted back.');
        $this->assertSame(2, $state['pressed'], 'The visitor\'s own vote is not announced as pressed.');
        $this->assertSame(0, $state['posts'], 'The widget asked the server something merely to draw itself.');
    }

    private function rating(string $probe, string $before = '', array $values = []): mixed
    {
        $stars = '';
        for ($value = 1; $value <= 5; ++$value) {
            $stars .= sprintf('<button type="button" class="rating-star" data-rating-target="star" data-action="rating#vote" data-rating-value-param="%d"></button>', $value);
        }

        return $this->observe(
            sprintf(
                '<div data-controller="rating"
                      data-rating-url-value="/rate"
                      data-rating-key-value="book-1"
                      data-rating-scale-value="%d"
                      data-rating-count-value="10"
                      data-rating-average-value="4"
                      data-rating-compact-value="%s"
                      data-rating-none-label-value="Pas encore note"
                      data-rating-one-label-value="%%count%% avis"
                      data-rating-many-label-value="%%count%% avis"
                      data-rating-error-label-value="Vote impossible"
                      data-rating-throttled-label-value="Revenez dans quelques minutes">
                    <div data-rating-target="row">%s</div>
                    <p data-rating-target="tally"></p>
                </div>',
                $values['scale'] ?? 5,
                $values['compact'] ?? 'false',
                $stars
            ),
            ['rating' => 'rating'],
            'const tick = () => new Promise((r) => setTimeout(r, 50)); ' . $probe,
            ['before' => self::SERVER . $before]
        );
    }
}
