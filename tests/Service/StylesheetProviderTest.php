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
    public function testGetStylesheetsReturnsUiBundlePublicStylesheets(): void
    {
        $provider = new StylesheetProvider();

        $this->assertSame(
            ['bundles/c975lui/css/animations.min.css', 'bundles/c975lui/css/styles.min.css'],
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
