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
use HeadlessChromium\Communication\Message;
use PHPUnit\Framework\Attributes\Group;

// The harness itself rather than a bundle's javascript: one tab carries the whole run, and a tab that goes away under it takes with it every scenario that had not run yet
// Seen for real on a suite whose Chrome was upgraded while it ran - the tab was destroyed between two of PaymentBundle's basket tests, and the one that came next answered "The session is destroyed" without ever running
#[Group('browser')]
class JsCaseRecoveryTest extends JsCase
{
    // The tab is taken away exactly as the run found it gone: between two scenarios, so the next one meets a dead session on its very first message
    public function testAScenarioIsRunAgainOnATabOfItsOwnWhenTheRunsTabGoesAway(): void
    {
        $session = $this->tab()->getSession();
        $session->getConnection()->sendMessageSync(new Message('Target.closeTarget', ['targetId' => $session->getTargetId()]));

        $this->assertSame(
            'here',
            $this->observe('<p id="after">here</p>', [], 'return root.querySelector("#after").textContent;'),
            'A tab that went away under the run is never reopened, so every scenario after it reads the same dead session.'
        );
    }
}
