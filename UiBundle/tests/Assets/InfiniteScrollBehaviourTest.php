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

// assets/js/infinite-scroll.js with the next page answered from the scenario itself, so what is checked is what the controller does with it
// A listing that grows on scroll fails in ways nobody reports: the same page appended twice, a listing that stops growing halfway, a "next" link left pointing at a url resolved against the wrong page. None of it throws, and the visitor simply stops finding things
#[Group('browser')]
class InfiniteScrollBehaviourTest extends JsCase
{
    // Pages answered in order, each naming the next
    private const string SERVER = 'window.__calls = [];
        window.__pages = {};
        window.fetch = (url, options) => {
            window.__calls.push({ url: String(url), headers: options?.headers ?? {} });
            const page = window.__pages[String(url)];

            return page === undefined
                ? Promise.resolve({ ok: false, status: 500, text: () => Promise.resolve("") })
                : Promise.resolve({ ok: true, status: 200, text: () => Promise.resolve(page) });
        };';

    // Only the items are taken from the fetched page: appending its listing whole would bring a second heading and a second list with them
    public function testOnlyTheItemsOfTheNextPageAreAppended(): void
    {
        $state = $this->listing(
            'root.querySelector("[data-infiniteScroll-target=next]").click();
             await tick();

             return {
                 items: [...root.querySelectorAll("[data-infiniteScroll-target=list] > li")].map((li) => li.textContent),
                 lists: root.querySelectorAll("[data-infiniteScroll-target=list]").length,
                 headings: root.querySelectorAll("h2").length,
                 count: root.querySelector("[data-infiniteScroll-target=count]").textContent,
             };'
        );

        $this->assertSame(['A', 'B', 'C', 'D'], $state['items'], 'The items of the next page were not appended to the one being read.');
        $this->assertSame(1, $state['lists'], 'The next page\'s own list came along with its items, so the page now holds two listings.');
        $this->assertSame(0, $state['headings'], 'The next page\'s heading was appended into the listing.');
        $this->assertSame('4', $state['count'], 'The count was not brought up to what is now on screen.');
    }

    // The address bar still shows the first page, which is not what the next one is relative to
    public function testTheNextUrlIsResolvedAgainstThePageItCameFrom(): void
    {
        $calls = $this->listing(
            'const next = root.querySelector("[data-infiniteScroll-target=next]");
             next.click();
             await tick();
             next.click();
             await tick();

             return { calls: window.__calls.map((c) => new URL(c.url).pathname + new URL(c.url).search), header: window.__calls[0].headers["X-Requested-With"] };'
        );

        $this->assertSame(['/list/page/2', '/list/page/3'], $calls['calls'], 'The second page\'s own "next" was resolved against the address bar rather than against the page it came from, so the listing loops or dead-ends.');
        $this->assertSame('XMLHttpRequest', $calls['header'], 'The request does not announce itself, so the server answers a whole page rather than the fragment.');
    }

    // Nothing left to load: the link goes, and with it the element the observer watches
    public function testAPageNamingNoNextTakesTheLinkAway(): void
    {
        $this->assertFalse(
            (bool) $this->listing(
                'const next = root.querySelector("[data-infiniteScroll-target=next]");
                 next.click();
                 await tick();
                 root.querySelector("[data-infiniteScroll-target=next]").click();
                 await tick();

                 return !!root.querySelector("[data-infiniteScroll-target=next]");'
            ),
            'The "next" link survives a page that names no next, so the listing goes on offering more where there is none.'
        );
    }

    // The link stays where it is so the visitor can retry, but the observer stops: a failure the scroll keeps re-firing would only repeat the same request
    public function testAFailedLoadLeavesTheLinkToRetryAndStopsAskingOnItsOwn(): void
    {
        $state = $this->listing(
            'window.__pages = {};
             const next = root.querySelector("[data-infiniteScroll-target=next]");
             next.click();
             await tick();
             const calls = window.__calls.length;
             window.scrollTo(0, document.body.scrollHeight);
             await tick();

             return { link: !!root.querySelector("[data-infiniteScroll-target=next]"), calls, after: window.__calls.length, busy: next.hasAttribute("aria-busy") };'
        );

        $this->assertTrue($state['link'], 'A failed load took the link away, leaving nothing to retry with and no way to reach the next page at all.');
        $this->assertSame(1, $state['calls'], 'The failed page was requested more than once for a single attempt.');
        $this->assertSame(1, $state['after'], 'The listing goes on repeating a request that already failed, every time the visitor scrolls.');
        $this->assertFalse($state['busy'], 'The link is left announcing itself as busy after the load ended.');
    }

    // A scroll heading for an anchor reaches a place appending items would push away from, and the visitor asking for the footer is not asking for more of the listing
    public function testAScrollHeadingForAnAnchorPausesTheListingUntilTheVisitorScrollsAgain(): void
    {
        $state = $this->listing(
            'document.dispatchEvent(new CustomEvent("anchor:scroll"));
             await tick();
             window.scrollTo(0, document.body.scrollHeight);
             await tick();
             const paused = window.__calls.length;

             document.dispatchEvent(new WheelEvent("wheel"));
             await tick();
             window.scrollTo(0, 0);
             window.scrollTo(0, document.body.scrollHeight);
             await tick();

             return { paused, resumed: window.__calls.length };'
        );

        $this->assertSame(0, $state['paused'], 'A scroll heading for an anchor still grew the listing, pushing the place being aimed at further away.');
        $this->assertGreaterThan(0, $state['resumed'], 'The listing never came back once the visitor started scrolling again.');
    }

    // A click leaves the focus on the link, which the loaded items now sit above - a keyboard visitor would otherwise have to walk back up to them
    public function testAClickMovesTheFocusOntoTheFirstItemItJustLoaded(): void
    {
        $this->assertSame(
            'C',
            $this->listing(
                'root.querySelector("[data-infiniteScroll-target=next]").click();
                 await tick();

                 return document.activeElement.textContent;'
            ),
            'The focus stayed on the link after a click, so a keyboard visitor is left below everything that was just loaded.'
        );
    }

    // A second request while one is in flight would append the same page twice
    public function testTwoClicksInARowLoadThePageOnlyOnce(): void
    {
        $this->assertSame(
            1,
            $this->listing(
                'const next = root.querySelector("[data-infiniteScroll-target=next]");
                 next.click();
                 next.click();
                 await tick();

                 return window.__calls.length;'
            ),
            'A second click while the first was still in flight sent the request again, which appends the same page twice.'
        );
    }

    private function listing(string $probe): mixed
    {
        $page = static fn (string $items, ?string $next): string => sprintf(
            '<h2>Listing</h2><ul data-infiniteScroll-target="list">%s</ul>%s',
            $items,
            null !== $next ? sprintf('<a data-infiniteScroll-target="next" href="%s">More</a>', $next) : ''
        );

        return $this->observe(
            sprintf(
                // The link is put well below the fold on purpose: the observer reaches 600px ahead of it, and a link within that of the viewport loads on its own before a scenario has said anything
                '<div data-controller="infiniteScroll">
                    <ul data-infiniteScroll-target="list"><li><a href="#a">A</a></li><li><a href="#b">B</a></li></ul>
                    <div style="height: 3000px"></div>
                    <span data-infiniteScroll-target="count">2</span>
                    <a data-infiniteScroll-target="next" href="/list/page/2" data-action="infiniteScroll#load">More</a>
                </div>'
            ),
            ['infiniteScroll' => 'infinite-scroll'],
            sprintf(
                'const tick = () => new Promise((r) => setTimeout(r, 60));
                 window.__pages[new URL("/list/page/2", location.href).href] = %s;
                 window.__pages[new URL("/list/page/3", location.href).href] = %s;
                 %s',
                json_encode($page('<li><a href="#c">C</a></li><li><a href="#d">D</a></li>', '3')),
                json_encode($page('<li><a href="#e">E</a></li>', null)),
                $probe
            ),
            ['before' => self::SERVER, 'settle' => 60]
        );
    }
}
