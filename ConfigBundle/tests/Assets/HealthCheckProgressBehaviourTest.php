<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/health-check-progress.js following a run whose answers the scenario writes itself
// The banner follows a queue the page knows nothing about, so every one of its states is a timer and an answer: a poll that fails must be silent and the next one must still come, and a run given up on server-side must say so rather than spin for ever. The one state that cannot be exercised here is the reload, which would take the page this suite shares out from under it - what is checked instead is that nothing reloads while the run is still short of what the page was rendered with
#[Group('browser')]
class HealthCheckProgressBehaviourTest extends JsCase
{
    // The interval the banner declares is the one it polls on, and it polls at all
    public function testTheRunIsFollowedOnTheIntervalTheBannerDeclares(): void
    {
        $this->assertGreaterThan(
            1,
            $this->progress('await wait(200); return window.__polls;'),
            'The run is not followed at all, so the banner spins over a queue nobody is watching.'
        );
    }

    // The run carries on regardless of a failed poll, and the next one is one interval away
    public function testAFailedPollIsSilentAndTheNextOneStillComes(): void
    {
        $state = $this->progress(
            'window.__fail = true;
             await wait(140);
             const during = window.__polls;
             window.__fail = false;
             await wait(140);

             return { during, after: window.__polls, said: root.querySelector("[data-health-check-progress-target=message]").textContent };'
        );

        $this->assertGreaterThan(0, $state['during'], 'Nothing was polled at all.');
        $this->assertGreaterThan($state['during'], $state['after'], 'A failed poll stopped the banner following the run, which then spins for ever.');
        $this->assertSame('En cours', $state['said'], 'A failed poll was reported to the reader, where the run simply carries on.');
    }

    // Given up on server-side, most often a worker nobody started: the banner has to say so and stop
    public function testARunGivenUpOnIsSaidAndStopsBeingFollowed(): void
    {
        $state = $this->progress(
            'window.__answer = { timedOut: true, done: 0 };
             await wait(140);
             const polls = window.__polls;
             await wait(160);

             return {
                 said: root.querySelector("[data-health-check-progress-target=message]").textContent,
                 spinner: !!root.querySelector("[data-health-check-progress-target=spinner]"),
                 warning: root.querySelector("[data-controller]").className,
                 stopped: window.__polls === polls,
             };'
        );

        $this->assertSame('Aucun worker', $state['said'], 'A run given up on says nothing, so the banner spins over a queue nothing is draining.');
        $this->assertFalse($state['spinner'], 'The spinner goes on turning over a run that has been given up on.');
        $this->assertStringContainsString('alert-warning', $state['warning'], 'The banner still reads as a run in progress.');
        $this->assertStringNotContainsString('alert-info', $state['warning']);
        $this->assertTrue($state['stopped'], 'The banner goes on polling a run it has already reported as given up on.');
    }

    // A run still short of what the page was rendered with changes nothing: the tables below are not out of date yet
    public function testARunShortOfWhatThePageHoldsLeavesTheScreenAlone(): void
    {
        $this->assertSame(
            'En cours',
            $this->progress(
                'window.__answer = { timedOut: false, done: 3, finished: false };
                 await wait(200);

                 return root.querySelector("[data-health-check-progress-target=message]").textContent;'
            ),
            'A run that has recorded nothing new since the page was drawn moved the screen anyway.'
        );
    }

    // Turbo caches the page as it stands, and a timer left running polls a run nobody is looking at any more
    public function testTheTimerDoesNotOutliveTheBanner(): void
    {
        $this->assertTrue(
            (bool) $this->progress(
                'const banner = root.querySelector("[data-controller]");
                 document.createElement("div").appendChild(banner);
                 await wait(60);
                 const polls = window.__polls;
                 await wait(200);

                 return window.__polls === polls;'
            ),
            'The timer outlived the banner, so a page left in the cache goes on polling.'
        );
    }

    private function progress(string $probe): mixed
    {
        return $this->observe(
            '<div class="alert alert-info" data-controller="health-check-progress"
                  data-health-check-progress-url-value="/progress"
                  data-health-check-progress-done-value="5"
                  data-health-check-progress-interval-value="50"
                  data-health-check-progress-timed-out-message-value="Aucun worker">
                <span data-health-check-progress-target="message">En cours</span>
                <span data-health-check-progress-target="spinner">...</span>
            </div>',
            ['health-check-progress' => 'health-check-progress'],
            'const wait = (ms) => new Promise((r) => setTimeout(r, ms)); ' . $probe,
            [
                // The progress route answered by the scenario, which is also what keeps this test off the network
                'before' => 'window.__polls = 0;
                    window.__fail = false;
                    window.__answer = { timedOut: false, done: 0, finished: false };
                    window.fetch = () => {
                        window.__polls += 1;

                        return window.__fail
                            ? Promise.reject(new Error("offline"))
                            : Promise.resolve({ ok: true, json: () => Promise.resolve(window.__answer) });
                    };',
                'settle' => 30,
            ]
        );
    }
}
