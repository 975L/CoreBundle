<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Model\SocialContentStatus;

// Implemented by SocialBundle, so the bundle owning a content shows in its own lists whether a post holds it - reserved by a draft, or gone out - without requiring SocialBundle. Injected as a nullable argument: null on a site without SocialBundle
interface SocialContentStatusProviderInterface
{
    // The status of each content a post holds, keyed by its id - a content no post holds being absent
    /**
     * @param list<string> $sourceIds
     *
     * @return array<string, SocialContentStatus>
     */
    public function getStatuses(string $sourceType, array $sourceIds): array;
}
