<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\PlayDurationProviderInterface;
use c975L\UiBundle\Service\PlayTicket;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// The proof a thing was played: only its own thing and holder, only once enough time went by on the server's clock, never the next day, and counted once
class PlayTicketTest extends TestCase
{
    private const string HOLDER = '0123456789abcdef0123456789abcdef';

    private function tickets(?int $duration = 600, string $secret = 'secret'): PlayTicket
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('70');

        $provider = $this->createStub(PlayDurationProviderInterface::class);
        $provider->method('supports')->willReturnCallback(static fn (string $ownerType): bool => 'book' === $ownerType);
        $provider->method('getPlayDuration')->willReturn($duration);

        return new PlayTicket($secret, $config, new ArrayAdapter(), [$provider]);
    }

    // A 600 s story at 70%: 420 s asked for, a few seconds short being forgiven
    public function testEnoughTimeMustGoBy(): void
    {
        $tickets = $this->tickets();
        $ticket = $tickets->issue('book', 12, self::HOLDER, 1000);

        $this->assertFalse($tickets->isHeard($ticket, 'book', 12, self::HOLDER, 1000 + 30));
        $this->assertFalse($tickets->isHeard($ticket, 'book', 12, self::HOLDER, 1000 + 400));
        $this->assertTrue($tickets->isHeard($ticket, 'book', 12, self::HOLDER, 1000 + 418));
    }

    public function testATicketIsNotKeptForLater(): void
    {
        $tickets = $this->tickets();
        $ticket = $tickets->issue('book', 12, self::HOLDER, 1000);

        $this->assertFalse($tickets->isHeard($ticket, 'book', 12, self::HOLDER, 1000 + 86400));
    }

    public function testATicketBelongsToItsThingAndHolder(): void
    {
        $tickets = $this->tickets();
        $ticket = $tickets->issue('book', 12, self::HOLDER, 1000);

        $this->assertFalse($tickets->isHeard($ticket, 'book', 12, 'u1', 1500));
        $this->assertFalse($tickets->isHeard($ticket, 'book', 12, null, 1500));
        $this->assertFalse($tickets->isHeard($ticket, 'book', 13, self::HOLDER, 1500));
        $this->assertFalse($tickets->isHeard($ticket, 'film', 12, self::HOLDER, 1500));
        $this->assertFalse($this->tickets(secret: 'other')->isHeard($ticket, 'book', 12, self::HOLDER, 1500));
        $this->assertFalse($tickets->isHeard('forged.' . hash('sha256', 'x'), 'book', 12, self::HOLDER, 1500));
    }

    // A listener nobody knows holds a ticket of their own all the same
    public function testAnAnonymousTicketIsHeard(): void
    {
        $tickets = $this->tickets();

        $this->assertTrue($tickets->isHeard($tickets->issue('book', 12, null, 1000), 'book', 12, null, 1500));
    }

    // A recording whose length cannot be read counts on the ticket alone, as a kind nobody measures
    public function testAThingOfUnknownLengthCountsOnTheTicket(): void
    {
        $tickets = $this->tickets(duration: null);
        $ticket = $tickets->issue('book', 12, null, 1000);

        $this->assertTrue($tickets->isHeard($ticket, 'book', 12, null, 1001));
        $this->assertFalse($tickets->isHeard($ticket, 'book', 12, null, 1000 + 86401));
    }

    // A kind no provider measures counts on the ticket alone
    public function testAKindNobodyMeasuresCountsOnTheTicket(): void
    {
        $tickets = $this->tickets();
        $ticket = $tickets->issue('film', 3, null, 1000);

        $this->assertTrue($tickets->isHeard($ticket, 'film', 3, null, 1001));
        $this->assertFalse($tickets->isHeard($ticket, 'film', 3, null, 1000 + 86401));
    }

    // Two tickets of the same second are still two
    public function testATicketIsSpentOnce(): void
    {
        $tickets = $this->tickets();
        $first = $tickets->issue('book', 12, null, 1000);
        $second = $tickets->issue('book', 12, null, 1000);

        $this->assertNotSame($first, $second);
        $this->assertTrue($tickets->spend($first));
        $this->assertFalse($tickets->spend($first));
        $this->assertTrue($tickets->spend($second));
    }

    public function testTheRatioIsReadAsAPercentage(): void
    {
        $this->assertSame(0.7, $this->tickets()->ratio());
    }
}
