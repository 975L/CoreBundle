<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Deliberately dependency-free - Twig instantiates every Extension eagerly with the environment on every request (even one rendering an error page or the profiler toolbar), so CollectionRuntime (and the DB query behind its CollectionSourceRegistry) only gets built lazily by Twig the first time a template actually calls collection_render_items()
class CollectionExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('collection_render_items', [CollectionRuntime::class, 'renderItems']),
            new TwigFunction('collection_render_entry', [CollectionRuntime::class, 'renderEntry']),
        ];
    }
}
