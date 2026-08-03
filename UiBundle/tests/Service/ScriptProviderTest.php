<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\ScriptProvider;
use PHPUnit\Framework\TestCase;

class ScriptProviderTest extends TestCase
{
    public function testGetScriptsReturnsUiBundlePublicController(): void
    {
        $provider = new ScriptProvider();

        $this->assertSame(['@c975l/ui-bundle/controllers.js'], $provider->getScripts());
    }

    public function testGetAdminScriptsReturnsUiBundleAdminController(): void
    {
        $provider = new ScriptProvider();

        $this->assertSame(['@c975l/ui-bundle/controllers-admin.js'], $provider->getAdminScripts());
    }
}
