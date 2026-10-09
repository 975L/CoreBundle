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

// Implement beside SocialContentSourceInterface so a post's content can be changed on its screen - another one chosen among those still free, or drawn again from the same group. Optional: a source without it keeps the content its draft was prepared with
interface BrowsableSocialContentSourceInterface extends SocialContentSourceInterface
{
    // The contents still free, the latest first, within the given groups - an empty list, or a source without groups, meaning all of them
    /**
     * @param list<string> $excludedIds
     * @param list<string> $scopeIds
     *
     * @return list<SocialContent>
     */
    public function findContents(array $excludedIds, array $scopeIds, int $limit): array;

    // The group a content belongs to (see ScopedSocialContentSourceInterface::getScopes()), null for a source without groups or a content gone
    public function getContentScope(string $sourceId): ?string;
}
