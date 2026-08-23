<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\ReviewVerifierInterface;
use c975L\UiBundle\Entity\Review;

// Whether a review can be marked as verified, asked once when it is submitted. Empty on every site bringing no verifier, and that is the ordinary case: a catalogue of books nobody buys here has no purchase to check a review against
class ReviewVerifierRegistry
{
    /**
     * @var ReviewVerifierInterface[]
     */
    private array $providers = [];

    // Called once per verifier by ReviewVerifierPass
    public function addProvider(ReviewVerifierInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // False for anything nobody answers for, which is what keeps the badge honest: it says the site checked, not that it had no way to
    public function verify(Review $review): bool
    {
        $ownerType = $review->getOwnerType();
        $ownerId = $review->getOwnerId();
        $email = $review->getAuthorEmail();

        if (null === $ownerType || null === $ownerId || null === $email || '' === trim($email)) {
            return false;
        }

        return $this->verifierFor($ownerType)?->hasObtained($ownerType, $ownerId, $email) ?? false;
    }

    private function verifierFor(string $ownerType): ?ReviewVerifierInterface
    {
        $matching = array_values(array_filter(
            $this->providers,
            static fn (ReviewVerifierInterface $provider): bool => $provider->supports($ownerType)
        ));

        // Fails loudly rather than letting one verifier silently win and the other become unreachable - same reading as FavoriteItemRegistry's
        if (\count($matching) > 1) {
            throw new \LogicException(sprintf('Several ReviewVerifierInterface providers support ownerType "%s": %s.', $ownerType, implode(', ', array_map(static fn (object $provider): string => $provider::class, $matching))));
        }

        return $matching[0] ?? null;
    }
}
