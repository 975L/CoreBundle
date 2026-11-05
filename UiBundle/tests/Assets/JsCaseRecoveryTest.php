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
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Session;
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

    // The same loss heard the other way round: Chrome answers -32001 while chrome-php still holds the session as alive, which closing the tab only produces now and then, so the session is given an id Chrome never issued
    public function testAScenarioIsRunAgainWhenChromeNoLongerKnowsTheSession(): void
    {
        $session = $this->tab()->getSession();
        $connection = $session->getConnection();

        $sessions = new \ReflectionProperty(Connection::class, 'sessions');
        $sessions->setValue($connection, [...$sessions->getValue($connection), 'unknown-to-chrome' => $session]);
        new \ReflectionProperty(Session::class, 'sessionId')->setValue($session, 'unknown-to-chrome');

        $this->assertSame(
            'here',
            $this->observe('<p id="after">here</p>', [], 'return root.querySelector("#after").textContent;'),
            'A session Chrome no longer knows reaches the run as a failed evaluation rather than as a destroyed target, and is not replayed.'
        );
    }
}
