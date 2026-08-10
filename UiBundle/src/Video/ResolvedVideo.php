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

    // Empty for every platform serving no guessable still - which is also how a caller knows not to offer the import at all (see VideoIframeType)
    public function posterUrls(): array
    {
        return $this->platform->posterUrls($this->id);
    }
}
