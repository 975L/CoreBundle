<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BlockRenderContext;
use PHPUnit\Framework\TestCase;

class BlockRenderContextTest extends TestCase
{
    // A public render reads from and writes to the cache, which is what every request but an editor's preview does
    public function testTheCacheIsEnabledUntilSomethingDisablesIt(): void
    {
        $this->assertFalse(new BlockRenderContext()->isCacheDisabled());
    }

    public function testDisablingTheCacheHoldsForTheWholeRender(): void
    {
        $context = new BlockRenderContext();
        $context->disableCache();

        $this->assertTrue($context->isCacheDisabled());
    }

    // Armed once for the render, never disarmed: a preview rendering a container and its slots asks again for each of them
    public function testDisablingTwiceLeavesItDisabled(): void
    {
        $context = new BlockRenderContext();
        $context->disableCache();
        $context->disableCache();

        $this->assertTrue($context->isCacheDisabled());
    }
}
