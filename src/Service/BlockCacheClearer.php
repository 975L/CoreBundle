<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\UiBundle\Service;

use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

// Makes the deployment itself invalidate block HTML, whose cache key holds no template version
// Autoconfigured through CacheClearerInterface, so it needs no tag in services.yaml
class BlockCacheClearer implements CacheClearerInterface
{
    public function __construct(private readonly BlockCacheInvalidator $blockCacheInvalidator)
    {
    }

    // No guard around the invalidation: Symfony's own Psr6CacheClearer already fails cache:clear outright when a configured pool is unreachable, so swallowing the error here would only hide a broken backend behind silently stale pages
    public function clear(string $cacheDir): void
    {
        $this->blockCacheInvalidator->invalidateAll();
    }
}
