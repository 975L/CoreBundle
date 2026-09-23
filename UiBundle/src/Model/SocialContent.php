<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// One content a SocialContentSourceInterface hands over for publication. The image comes both ways because the networks differ: Bluesky wants its bytes uploaded (imagePath, read from disk rather than the site fetching itself), Meta wants a public url it downloads on its own (imageUrl)
final class SocialContent
{
    /**
     * @param array<string, string> $variables What the post text may carry beside title and url ("category", "description"...), each one replacing its "{name}" in the site's template
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $imagePath = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $imageAlt = null,
        public readonly array $variables = [],
    ) {
    }
}
