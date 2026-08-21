<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Video\VideoPlatform;
use Twig\Attribute\AsTwigFilter;

class VideoExtension
{
    // Turns whatever an editor pasted into the canonical embed url of the platform it belongs to - the address bar's own "/watch?v=", a share link, an embed url already, all carrying the same id (see VideoPlatform::resolve())
    // Privacy is what the canonical url buys: YouTube lands on youtube-nocookie.com, Vimeo carries "dnt=1". A url belonging to no declared platform is left exactly as it was, which is what keeps this filter safe to apply to anything
    // Player parameters on an already-formed embed url ("?autoplay=1", "?start=30") do not survive the rewrite: only the id is read back. The same was already true of the "/watch?t=42s" urls this filter was written for, and an embed's options belong to the block's own form rather than to a url pasted into it
    #[AsTwigFilter('privacy_embed_url')]
    public function toPrivacyEmbedUrl(?string $url): ?string
    {
        return VideoPlatform::resolve($url)?->embedUrl() ?? $url;
    }

    // The other side of the same coin: where the video is watched on the platform itself, for a page offering a way out to the channel, the comments or the full screen. Read off whatever the editor stored - an embed url as readily as a share link - and, like the filter above, a url belonging to no declared platform comes back untouched
    #[AsTwigFilter('video_watch_url')]
    public function toWatchUrl(?string $url): ?string
    {
        return VideoPlatform::resolve($url)?->watchUrl() ?? $url;
    }
}
