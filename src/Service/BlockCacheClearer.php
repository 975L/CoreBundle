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

// A block's rendered HTML is cached with no expiry and no template version in its key (see BlockExtension::renderBlock()), and BlockCacheInvalidationListener only invalidates it when a Block or Media entity is flushed - so a deployment that merely ships a changed Twig template left every page serving the previous markup indefinitely, until someone hit the dashboard's "clear cache" shortcut by hand. Hooking cache:clear makes the deployment itself the signal, instead of each site repeating that step in its own deploy script.
// Registered through FrameworkBundle's autoconfiguration of CacheClearerInterface ("kernel.cache_clearer"), so it needs no tag of its own in services.yaml
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
