<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\StylesheetProvider;
use PHPUnit\Framework\TestCase;

class StylesheetProviderTest extends TestCase
{
    // Last one out is the @font-face sheet FontCssListener compiles from the admin-uploaded Font rows - its position doesn't matter, @font-face rules don't participate in the cascade
    public function testGetStylesheetsReturnsUiBundlePublicStylesheets(): void
    {
        $provider = new StylesheetProvider();

        $this->assertSame(
            ['bundles/c975lui/css/animations.min.css', 'bundles/c975lui/css/styles.min.css', 'bundles/build/site-fonts-uploaded.css'],
            $provider->getStylesheets()
        );
    }

    public function testGetManagementStylesheetsReturnsUiBundleAdminStylesheet(): void
    {
        $provider = new StylesheetProvider();

        $this->assertSame(
            ['bundles/c975lui/css/management.min.css'],
            $provider->getManagementStylesheets()
        );
    }
}
