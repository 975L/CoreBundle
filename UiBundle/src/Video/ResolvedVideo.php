<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Video;

// What a pasted url turns out to be: a platform, the id it gives the video, and the handful of query parameters without which its player would not play (see VideoPlatform::carriedParams()) - the embed url is rebuilt from them on demand rather than kept, so a platform changing its url scheme never leaves a stale one behind (see VideoPlatform::embedUrl())
final readonly class ResolvedVideo
{
    public function __construct(
        public VideoPlatform $platform,
        public string $id,
        public array $carriedParams = [],
    ) {
    }

    public function embedUrl(): string
    {
        return $this->platform->embedUrl($this->id, $this->carriedParams);
    }

    // Where the video is watched on the platform itself, the embed url above being where it is played inside a page
    // The carried params go with it, exactly as they go to the embed url: a playlist and an unlisted video have no watchable address without them
    public function watchUrl(): string
    {
        return $this->platform->watchUrl($this->id, $this->carriedParams);
    }

    // Empty for every platform serving no guessable still - which is also how a caller knows not to offer the import at all (see VideoIframeType)
    public function posterUrls(): array
    {
        return $this->platform->posterUrls($this->id);
    }
}
