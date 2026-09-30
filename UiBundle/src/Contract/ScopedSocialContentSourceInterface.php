<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Model\SocialContent;

// Implement beside SocialContentSourceInterface when a source's contents fall into groups a publication slot may be narrowed to - a gallery's categories. Optional: a source without groups keeps implementing the base interface alone
interface ScopedSocialContentSourceInterface extends SocialContentSourceInterface
{
    // The groups a slot may pick among, labels keyed by id
    /** @return array<string, string> */
    public function getScopes(): array;

    // The next content taken from the given groups only, null when none is left there - an empty list meaning every group, as getNextContent() does
    /**
     * @param list<string> $excludedIds
     * @param list<string> $scopeIds
     */
    public function getNextScopedContent(array $excludedIds, array $scopeIds): ?SocialContent;
}
