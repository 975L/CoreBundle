<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// Implement to tell how long a thing of your kind plays, read by the server itself, so a play of it only counts once enough of it really went by (see PlayTicket). A kind no provider supports is counted on the ticket alone, with no time asked for
#[AutoconfigureTag('c975l.play_duration_provider')]
interface PlayDurationProviderInterface
{
    // The short stable string the plays of this kind are counted under ("book"...), the same one its favourites use
    public function supports(string $ownerType): bool;

    // In seconds, null for a thing with no recording, or one that cannot be read: such a play is never counted
    public function getPlayDuration(string $ownerType, int $ownerId): ?int;
}
