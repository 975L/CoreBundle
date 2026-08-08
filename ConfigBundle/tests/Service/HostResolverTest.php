<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\HostResolver;
use PHPUnit\Framework\TestCase;

class HostResolverTest extends TestCase
{
    // What the stubbed dns_get_record() at the foot of this file answers with, and what it was asked - HostResolver calls the function unqualified, so one declared in its own namespace shadows the global one and keeps these tests off the network
    public static bool $stubbed = true;

    /** @var list<array<string, mixed>>|false */
    public static array | false $records = false;

    /** @var list<array{0: string, 1: int}> */
    public static array $calls = [];

    protected function setUp(): void
    {
        self::$stubbed = true;
        self::$records = false;
        self::$calls = [];
    }

    // The whole point of the class: a host served over IPv6 only exists, and gethostbyname() would report it as not existing at all
    public function testAHostCarryingOnlyAnAaaaRecordResolves(): void
    {
        self::$records = [['host' => 'example.com', 'type' => 'AAAA', 'ipv6' => '2001:db8::1']];

        $this->assertTrue((new HostResolver())->resolves('example.com'));
    }

    public function testAHostCarryingAnARecordResolves(): void
    {
        self::$records = [['host' => 'example.com', 'type' => 'A', 'ip' => '93.184.216.34']];

        $this->assertTrue((new HostResolver())->resolves('example.com'));
    }

    // Both record types are asked for in one call, an A-only lookup being what misses an IPv6-only host
    public function testBothRecordTypesAreAskedForInOneCall(): void
    {
        (new HostResolver())->resolves('example.com');

        $this->assertSame([['example.com', \DNS_A | \DNS_AAAA]], self::$calls);
    }

    public function testAHostAnsweringNoRecordDoesNotResolve(): void
    {
        self::$records = [];

        $this->assertFalse((new HostResolver())->resolves('www.example.com'));
    }

    // A failed lookup is the very answer wanted here, never an error to raise
    public function testAFailedLookupDoesNotResolve(): void
    {
        self::$records = false;

        $this->assertFalse((new HostResolver())->resolves('www.example.com'));
    }

    // The real lookup, kept as the one check that the silenced call answers at all: a name reserved by RFC 2606 exists nowhere, online or off
    public function testAReservedNameDoesNotResolve(): void
    {
        self::$stubbed = false;

        $this->assertFalse((new HostResolver())->resolves('c975l-health-check.invalid'));
    }
}

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Tests\Service\HostResolverTest;

// Shadows the global function for this namespace alone, HostResolver being the only class of the bundle reading dns
function dns_get_record(string $hostname, int $type): array | false
{
    HostResolverTest::$calls[] = [$hostname, $type];

    return HostResolverTest::$stubbed ? HostResolverTest::$records : \dns_get_record($hostname, $type);
}
