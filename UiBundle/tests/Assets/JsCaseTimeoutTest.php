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
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Group;

// The harness itself: a scenario awaiting something that never settles has to fail with the harness's own message, not with chrome-php's timeout on the evaluated script
#[Group('browser')]
class JsCaseTimeoutTest extends JsCase
{
    // The probe empties its fixture first, so the scenario left hanging on the shared page leaves nothing behind for the next one
    public function testAScenarioThatNeverSettlesFailsWithTheHarnessMessage(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The scenario never answered within 5000 ms');

        $this->observe('<p>hanging</p>', [], 'root.replaceChildren(); await new Promise(() => {});');
    }

    // A scenario given up on still answers once it settles, and the next scenario must not read that answer as its own
    public function testALateAnswerNeverReachesTheNextScenario(): void
    {
        try {
            $this->observe('<p>late</p>', [], 'await new Promise((r) => setTimeout(r, 300)); return "late";', ['timeout' => 100]);
            $this->fail('The scenario answered within its timeout, so this proves nothing.');
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString('never answered within 100 ms', $error->getMessage());
        }

        $this->assertSame('own', $this->observe('<p>own</p>', [], 'await new Promise((r) => setTimeout(r, 500)); return "own";'));
    }
}
