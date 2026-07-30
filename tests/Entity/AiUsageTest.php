<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\AiUsage;
use PHPUnit\Framework\TestCase;

class AiUsageTest extends TestCase
{
    public function testAddUsageAccumulatesTokensAndRequestCount(): void
    {
        $usage = new AiUsage();

        $usage->addUsage(100, 50);
        $usage->addUsage(20, 10);

        $this->assertSame(120, $usage->getInputTokens());
        $this->assertSame(60, $usage->getOutputTokens());
        $this->assertSame(2, $usage->getRequestCount());
    }

    public function testAddUsageClearsAPreviouslyRecordedFailure(): void
    {
        $usage = (new AiUsage())->recordFailure('provider unavailable');

        $usage->addUsage(10, 5);

        $this->assertNull($usage->getLastFailureAt());
        $this->assertNull($usage->getLastFailureMessage());
    }

    public function testRecordFailureStoresTheMessageAndTimestamp(): void
    {
        $usage = (new AiUsage())->recordFailure('invalid key');

        $this->assertSame('invalid key', $usage->getLastFailureMessage());
        $this->assertInstanceOf(\DateTimeImmutable::class, $usage->getLastFailureAt());
    }

    public function testClearFailureResetsBothFields(): void
    {
        $usage = (new AiUsage())->recordFailure('invalid key');

        $usage->clearFailure();

        $this->assertNull($usage->getLastFailureAt());
        $this->assertNull($usage->getLastFailureMessage());
    }
}
