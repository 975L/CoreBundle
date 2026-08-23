<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement to say whether the person leaving a review really got hold of what they are reviewing - auto-discovered by interface, no tag needed, see ReviewVerifierPass
// Declared here because this bundle owns the review and the "vérifié" badge it prints, and knows nothing of what is sold, lent or downloaded on the site: a bundle that does answers for its own kind of thing, and every other kind stays unverified
interface ReviewVerifierInterface
{
    // The owner vocabulary this verifier answers for ("shop_product"...), the same word the review is filed under
    public function supports(string $ownerType): bool;

    // Whether that address got hold of that thing. False on anything it cannot establish: the badge says the site checked, so an unanswerable question is not a yes
    public function hasObtained(string $ownerType, int $ownerId, string $email): bool;
}
