<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\MessengerStatusProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

class MessengerStatusProviderTest extends TestCase
{
    public function testGetStatusKey(): void
    {
        $this->assertSame('messenger', new MessengerStatusProvider()->getStatusKey());
    }

    // A site without Messenger, or with the bundle installed in an application that never configured it, still has everything else worth reporting
    public function testASiteWithoutMessengerReportsNothingRatherThanFailing(): void
    {
        $this->assertSame(['transports' => [], 'failureTransport' => null], new MessengerStatusProvider()->getStatusData());
    }

    // The locator holds every transport twice, under its service id and under its short name: reported twice, a console would show the same queue as two
    public function testATransportIsCountedOnceUnderItsShortName(): void
    {
        $async = $this->countable(3);
        $locator = $this->locator(['messenger.transport.async' => $async, 'async' => $async]);

        $data = new MessengerStatusProvider($locator)->getStatusData();

        $this->assertSame(['async' => 3], $data['transports']);
    }

    // The whole point of the section: which queue holds what has failed for good, so a count of zero elsewhere is not read as "nothing wrong"
    public function testTheFailureTransportIsNamed(): void
    {
        $failed = $this->countable(28);
        $locator = $this->locator(['async' => $this->countable(0), 'failed' => $failed]);

        $data = new MessengerStatusProvider($locator, $failed)->getStatusData();

        $this->assertSame('failed', $data['failureTransport']);
        $this->assertSame(['async' => 0, 'failed' => 28], $data['transports']);
    }

    // Nothing configured to receive the failures is itself worth knowing: those messages are dropped rather than kept
    public function testASiteWithoutFailureTransportNamesNone(): void
    {
        $data = new MessengerStatusProvider($this->locator(['async' => $this->countable(0)]))->getStatusData();

        $this->assertNull($data['failureTransport']);
    }

    // "Nothing waiting" and "no way to tell" are the two answers this section exists to separate, so a scheduler transport is left out rather than reported as zero
    public function testATransportThatCannotCountItselfIsLeftOut(): void
    {
        $locator = $this->locator(['scheduler_site' => $this->uncountable(), 'async' => $this->countable(1)]);

        $this->assertSame(['async' => 1], new MessengerStatusProvider($locator)->getStatusData()['transports']);
    }

    // A broker that is down costs its own line, not the whole section: the other transports are still worth reporting
    public function testATransportThatThrowsCostsOnlyItsOwnLine(): void
    {
        $locator = $this->locator(['broken' => $this->throwing(), 'async' => $this->countable(2)]);

        $this->assertSame(['async' => 2], new MessengerStatusProvider($locator)->getStatusData()['transports']);
    }

    /** @param array<string, ReceiverInterface> $receivers */
    private function locator(array $receivers): ServiceProviderInterface
    {
        return new TestReceiverLocator($receivers);
    }

    private function countable(int $count): ReceiverInterface & MessageCountAwareInterface
    {
        return new CountableTestReceiver($count);
    }

    private function uncountable(): ReceiverInterface
    {
        return new UncountableTestReceiver();
    }

    private function throwing(): ReceiverInterface & MessageCountAwareInterface
    {
        return new ThrowingTestReceiver();
    }
}

// The locator MessengerPass builds, holding each transport twice - under its service id and under its short name
class TestReceiverLocator implements ServiceProviderInterface
{
    /** @param array<string, ReceiverInterface> $receivers */
    public function __construct(private readonly array $receivers)
    {
    }

    public function get(string $id): mixed
    {
        return $this->receivers[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->receivers[$id]);
    }

    /** @return array<string, string> */
    public function getProvidedServices(): array
    {
        return array_map(static fn (ReceiverInterface $receiver) => $receiver::class, $this->receivers);
    }
}

// Only getMessageCount() is ever called: consuming is what a worker does, and this provider never consumes
abstract class TestReceiver implements ReceiverInterface
{
    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}

class CountableTestReceiver extends TestReceiver implements MessageCountAwareInterface
{
    public function __construct(private readonly int $count)
    {
    }

    public function getMessageCount(): int
    {
        return $this->count;
    }
}

// A scheduler transport: it receives, and has no way to say how much is waiting
class UncountableTestReceiver extends TestReceiver
{
}

// A transport whose broker is down
class ThrowingTestReceiver extends TestReceiver implements MessageCountAwareInterface
{
    public function getMessageCount(): int
    {
        throw new \RuntimeException('Connection refused');
    }
}
