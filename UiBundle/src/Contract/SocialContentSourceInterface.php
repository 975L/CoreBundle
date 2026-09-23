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

// Implement to hand SocialBundle's scheduled publication the contents to post on the site's networks - a photo, a story, a product. Declared here rather than in SocialBundle so the bundle owning the content implements it without requiring SocialBundle, the one publishing it keeping track of what went out where (auto-discovered by interface, no tag needed)
interface SocialContentSourceInterface
{
    // Stored beside each posted id ("gallery_media"), so two sources can hand out the same numeric id without one blocking the other
    public function getSourceType(): string;

    // Days after which a posted content may be offered again, null for never - a story told again a month later is a reminder, the same photograph twice is a repeat
    public function getRepeatAfterDays(): ?int;

    // The next content to post, null when there is none left - the ids already posted are the publisher's to know, this source only picks among the others
    /** @param list<string> $excludedIds */
    public function getNextContent(array $excludedIds): ?SocialContent;

    // One content read again, null once it is gone: a post reviewed tomorrow goes out with the image as it then is, a photograph moved to another gallery having taken its file along
    public function getContent(string $sourceId): ?SocialContent;
}
