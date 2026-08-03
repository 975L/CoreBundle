<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\ThemeVariablesStylesheetProvider;
use PHPUnit\Framework\TestCase;

class ThemeVariablesStylesheetProviderTest extends TestCase
{
    // Alone in its own provider on purpose, so services.yaml can give it a priority of its own between the bundles' 100 and the app's -100 - see the class comment
    public function testGetStylesheetsReturnsOnlyTheCompiledThemeFile(): void
    {
        $this->assertSame(
            ['bundles/build/site-theme.css'],
            (new ThemeVariablesStylesheetProvider())->getStylesheets()
        );
    }
}
