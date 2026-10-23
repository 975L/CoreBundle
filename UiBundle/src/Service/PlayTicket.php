<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\PlayDurationProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

// The proof a thing was really played, kept by the page or the app between its start and the moment it counts: signed by the server and holding when it started, so the time that went by is the server's own measure - jumping to the end gives nothing. Bound to its holder when there is one, so a ticket cannot be handed around
class PlayTicket
{
    // Past this, a ticket is too old to be the same playthrough: it cannot be kept for the next day
    private const int MAX_PAUSE = 3600;

    // Seconds a playthrough is allowed short of its part: the ticket is asked for once the player has already started, and the clock only reads whole seconds
    private const int SLACK = 5;

    // How long a ticket of a kind with no known length may wait before it counts
    private const int MAX_AGE = 86400;

    /** @param iterable<PlayDurationProviderInterface> $durationProviders */
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
        private readonly ConfigServiceInterface $configService,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        #[AutowireIterator('c975l.play_duration_provider')]
        private readonly iterable $durationProviders = [],
    ) {
    }

    public function issue(string $ownerType, int $ownerId, ?string $holder, ?int $now = null): string
    {
        // Two listeners nobody knows starting the same story in the same second still get two tickets, each counting once
        $payload = implode('|', [$ownerType, $ownerId, $holder ?? '', $now ?? time(), bin2hex(random_bytes(6))]);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . $this->sign($payload);
    }

    // The part of a thing that must have played before it counts, between 0.1 and 1 - which is also where the page asks for it to be counted
    public function ratio(): float
    {
        return min(1.0, max(0.1, (int) $this->configService->get('ui-play-ratio') / 100));
    }

    // Whether this ticket was issued for this thing and this holder, and enough of it played by now on the server's clock
    public function isHeard(string $ticket, string $ownerType, int $ownerId, ?string $holder, ?int $now = null): bool
    {
        $startedAt = $this->startedAt($ticket, $ownerType, $ownerId, $holder);
        if (null === $startedAt) {
            return false;
        }
        $elapsed = ($now ?? time()) - $startedAt;

        $provider = $this->durationProvider($ownerType);
        if (null === $provider) {
            return $elapsed >= 0 && $elapsed <= self::MAX_AGE;
        }
        $duration = $provider->getPlayDuration($ownerType, $ownerId);

        return null !== $duration && $duration > 0
            && $elapsed + self::SLACK >= $duration * $this->ratio()
            && $elapsed <= 3 * $duration + self::MAX_PAUSE;
    }

    // Marks a ticket as used, answering false when it already was: a play counts once per ticket. Kept as long as a ticket can live at most
    public function spend(string $ticket): bool
    {
        $fresh = false;
        $this->cache->get('ui_play_ticket_' . hash('xxh128', $ticket), static function (ItemInterface $item) use (&$fresh): bool {
            $item->expiresAfter(self::MAX_AGE);
            $fresh = true;

            return true;
        });

        return $fresh;
    }

    // When the playthrough started, null for a ticket that is not this thing's, not this holder's, or forged
    private function startedAt(string $ticket, string $ownerType, int $ownerId, ?string $holder): ?int
    {
        $parts = explode('.', $ticket, 2);
        if (2 !== \count($parts)) {
            return null;
        }
        $payload = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if (false === $payload || !hash_equals($this->sign($payload), $parts[1])) {
            return null;
        }
        $fields = explode('|', $payload);
        if (5 !== \count($fields) || $fields[0] !== $ownerType || (int) $fields[1] !== $ownerId || $fields[2] !== ($holder ?? '')) {
            return null;
        }

        return (int) $fields[3];
    }

    private function durationProvider(string $ownerType): ?PlayDurationProviderInterface
    {
        foreach ($this->durationProviders as $provider) {
            if ($provider->supports($ownerType)) {
                return $provider;
            }
        }

        return null;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'ui-play|' . $payload, $this->secret);
    }
}
