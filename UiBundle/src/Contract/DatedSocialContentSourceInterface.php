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

// Implement beside SocialContentSourceInterface when a content only suits the day it goes out - an anniversary, a day of the year. SocialBundle then chooses it by the post's planned date, a day without content being passed over rather than ending the series. Optional: a source without dates keeps implementing the base interface alone
interface DatedSocialContentSourceInterface extends SocialContentSourceInterface
{
    // The content to post on $date, in the site's time zone, null when that day has none left - asked in place of getNextContent() and of the groups of ScopedSocialContentSourceInterface whenever the planned date is known
    /** @param list<string> $excludedIds */
    public function getContentForDate(\DateTimeImmutable $date, array $excludedIds): ?SocialContent;
}
