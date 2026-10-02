<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

interface PageOverlayProviderInterface
{
    // What a bundle draws over every admin page, kept across menu clicks (e.g. UiBundle's Donovan panel), in the dashboard widgets' shape; [] when nothing to show
    /** @return list<array{template: string, context: array<string, mixed>}> */
    public function getPageOverlays(): array;
}
