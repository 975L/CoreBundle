<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\HealthCheck;
use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    // The agent every client sends, and the one RateLimitListener reads back - the two have to name the same string or a run counts against the site it measures
    public function testTheUserAgentItSendsIsTheOneItRecognises(): void
    {
        $this->assertTrue(HealthCheck::isProbe(HealthCheck::USER_AGENT));
    }

    // Kept in the "Mozilla/5.0 (compatible; ...)" shape crawlers have used since Googlebot, which far fewer filters reject outright than a bare library default, and naming where it comes from
    public function testTheUserAgentSaysWhoIsCallingAndWhere(): void
    {
        $this->assertStringStartsWith('Mozilla/5.0 (compatible; ', HealthCheck::USER_AGENT);
        $this->assertStringContainsString('https://github.com/975L/ConfigBundle', HealthCheck::USER_AGENT);
    }

    // Matched anywhere in the header rather than compared whole, so a proxy appending its own token to the agent does not turn a probe into ordinary traffic
    public function testAProbeIsRecognisedWhateverElseTheHeaderCarries(): void
    {
        $this->assertTrue(HealthCheck::isProbe('c975LHealthCheck/1.0 via some-proxy/2'));
    }

    // A request carrying no agent at all reaches this before the firewall, where nothing has been resolved yet
    public function testAnythingElseIsOrdinaryTraffic(): void
    {
        $this->assertFalse(HealthCheck::isProbe(null));
        $this->assertFalse(HealthCheck::isProbe(''));
        $this->assertFalse(HealthCheck::isProbe('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
    }
}
