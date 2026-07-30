<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Management\UiFormThemeProvider;
use PHPUnit\Framework\TestCase;

class UiFormThemeProviderTest extends TestCase
{
    public function testGetFormThemesReturnsUiBundleOwnThemes(): void
    {
        $provider = new UiFormThemeProvider();

        $this->assertSame(
            [
                '@c975LUi/form/block_theme.html.twig',
                '@c975LUi/form/icon_picker_theme.html.twig',
                '@c975LUi/form/media_usages_theme.html.twig',
            ],
            $provider->getFormThemes()
        );
    }
}
