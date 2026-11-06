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
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\OperationTimedOut;
use PHPUnit\Framework\TestCase;

// Which failures of a browser launch the harness tries once more, without starting any browser
class JsCaseLaunchTest extends TestCase
{
    // Chrome's startup timeout, wrapped by chrome-php into a plain RuntimeException, is tried again
    public function testAStartupTimeoutWrappedByChromePhpIsTriedAgain(): void
    {
        $this->assertTrue($this->diedOnItsWayUp(new \RuntimeException('Cannot start browser', 0, new OperationTimedOut())));
    }

    // The timeout and the communication failure thrown as they are are tried again
    public function testATimeoutOrACommunicationFailureIsTriedAgain(): void
    {
        $this->assertTrue($this->diedOnItsWayUp(new OperationTimedOut()));
        $this->assertTrue($this->diedOnItsWayUp(new CommunicationException()));
    }

    // Any other failure, a genuinely broken Chrome among them, fails at once
    public function testAnyOtherFailureIsNotTriedAgain(): void
    {
        $this->assertFalse($this->diedOnItsWayUp(new \RuntimeException('Devtools could not start')));
    }

    // The private predicate the launch relies on
    private function diedOnItsWayUp(\Throwable $exception): bool
    {
        return new \ReflectionMethod(JsCase::class, 'diedOnItsWayUp')->invoke(null, $exception);
    }
}
