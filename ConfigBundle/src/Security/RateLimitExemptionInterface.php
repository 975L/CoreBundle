<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

// Paths a bundle keeps out of the front rate limiter, signed-in pages of its own outside /management or a machine's API, collected by TaggedInterfacePass
interface RateLimitExemptionInterface
{
    // Whether a request to this path never counts against its caller, decided on the path alone: the limiter runs before any session exists
    public function isExempt(string $path): bool;
}
