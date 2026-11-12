<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Cache tags gathered during a flush and emptied once its transaction is committed: invalidated inside it, a concurrent request would cache the old state again, with no expiry. The listener using it declares the postFlush event and answers tagCache()
trait PostFlushTagInvalidationTrait
{
    /** @var array<string, true> */
    private array $pendingTags = [];

    // Keeps the tags until the flush is committed, each one once
    /** @param string[] $tags */
    private function queueTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->pendingTags[$tag] = true;
        }
    }

    // Empties the tags gathered, once the transaction is committed
    public function postFlush(): void
    {
        if ([] === $this->pendingTags) {
            return;
        }

        $tags = array_keys($this->pendingTags);
        $this->pendingTags = [];
        $this->tagCache()->invalidateTags($tags);
    }

    // The pool the tags are emptied from
    abstract private function tagCache(): TagAwareCacheInterface;
}
