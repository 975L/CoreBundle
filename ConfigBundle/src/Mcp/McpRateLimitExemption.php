<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Mcp;

use c975L\ConfigBundle\Controller\McpController;
use c975L\ConfigBundle\Security\RateLimitExemptionInterface;

// An agent calls several tools in a row: its endpoint answers its token, not the front limiter
class McpRateLimitExemption implements RateLimitExemptionInterface
{
    public function isExempt(string $path): bool
    {
        return McpController::PATH === $path;
    }
}
