<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

// How much this site has left waiting in its queues, and how much it has given up on. The one state of a site that nothing else reports: a stopped worker raises no error, turns no health check red and answers every page normally - mails, scheduled commands and queued jobs simply stop coming out, and the queue they pile up in is the only place that says so.
//
// Two readings rather than one, because they fail in opposite ways: a message in the failure transport has exhausted its retries and waits to be replayed by hand, while messages piling up in an ordinary transport have never been tried at all. A site with a dead worker shows zero failures, which is exactly what makes the failure count alone misleading.
//
// Counts only, never a message: what is queued is the site's business, and a payload travels.
class MessengerStatusProvider implements StatusProviderInterface
{
    /**
     * Both nullable: a site can run without Messenger at all, and one running it without a failure transport
     * has no such alias - neither is a reason to fail the whole report (see the "@?" in services.yaml).
     */
    public function __construct(
        private readonly ?ServiceProviderInterface $receiverLocator = null,
        private readonly ?ReceiverInterface $failureReceiver = null,
    ) {
    }

    public function getStatusKey(): string
    {
        return 'messenger';
    }

    /**
     * One count per countable transport, plus the name of the one messages go to when they have failed for good.
     *
     * Transports that cannot count themselves (the scheduler's, chiefly) are left out rather than reported as
     * zero: "nothing waiting" and "no way to tell" are the two answers this whole section exists to separate.
     *
     * @return array<string, mixed>
     */
    public function getStatusData(): array
    {
        if (null === $this->receiverLocator) {
            return ['transports' => [], 'failureTransport' => null];
        }

        $counts = [];
        $failureTransport = null;

        // The locator holds each transport twice, under its service id and under its short name (see MessengerPass), the short name second. Keying by object rather than by name is what collapses the pair, and keeps the short name - the one a maintainer types after "messenger:consume"
        foreach (array_keys($this->receiverLocator->getProvidedServices()) as $name) {
            try {
                $receiver = $this->receiverLocator->get($name);
            } catch (\Throwable) {
                continue;
            }

            if (null !== $this->failureReceiver && $receiver === $this->failureReceiver) {
                $failureTransport = $name;
            }

            if (!$receiver instanceof MessageCountAwareInterface) {
                continue;
            }

            try {
                $counts[spl_object_id($receiver)] = [$name, $receiver->getMessageCount()];
            } catch (\Throwable) {
                // A transport whose broker is down costs its own line and not the section: the others are still worth reporting, and this one's silence is already visible as a missing name
                continue;
            }
        }

        $transports = [];
        foreach ($counts as [$name, $count]) {
            $transports[$name] = $count;
        }

        ksort($transports);

        return [
            'transports' => $transports,
            'failureTransport' => $failureTransport,
        ];
    }
}
