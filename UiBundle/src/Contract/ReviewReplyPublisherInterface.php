<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Entity\Review;

// Implement to carry an answer written in the back office back to the platform the review came from - auto-discovered by interface, no tag needed, see ReviewReplyPublisherPass
// Declared here rather than where the platforms live because this bundle owns the review and its moderation screen: it has to be able to ask "can this one be answered?" on a site where no bundle brings any platform at all, and get "no" rather than a missing service
interface ReviewReplyPublisherInterface
{
    // Whether this review belongs to a platform this publisher speaks for and still holds the credentials to reach, which is what tells the admin screen to offer the reply field
    public function supports(Review $review): bool;

    // Publishes the review's current reply; an empty one removes it, the site never keeping an answer its author withdrew. Throws rather than reporting failure - a reply stored here but refused by the platform would show the visitor an answer the author never received
    public function publish(Review $review): void;
}
