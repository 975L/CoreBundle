<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\ReviewReplyPublisherInterface;
use c975L\UiBundle\Entity\Review;

// Which publisher, if any, speaks for a review's platform. Empty on every site bringing none, and that is the ordinary case: a review written here is answered here, nothing to push anywhere
class ReviewReplyRegistry
{
    /**
     * @var ReviewReplyPublisherInterface[]
     */
    private array $providers = [];

    // Called once per publisher by ReviewReplyPublisherPass
    public function addProvider(ReviewReplyPublisherInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Whether the review can be answered at all: a local review always can, its answer going no further than this site
    public function supports(Review $review): bool
    {
        return $review->isLocal() || null !== $this->publisherFor($review);
    }

    // Pushes the answer out when a platform is waiting for it, and does nothing at all for a local review - which is not a failure but the whole point: it was already stored by the caller
    public function publish(Review $review): void
    {
        $this->publisherFor($review)?->publish($review);
    }

    private function publisherFor(Review $review): ?ReviewReplyPublisherInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($review)) {
                return $provider;
            }
        }

        return null;
    }
}
