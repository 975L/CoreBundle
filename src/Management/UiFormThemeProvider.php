<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\UiBundle\Contract\FormThemeProviderInterface;

// UiBundle's own EasyAdmin form theme contributions - see FormThemeProviderInterface for why this exists instead of the app-wide twig.form_themes config
class UiFormThemeProvider implements FormThemeProviderInterface
{
    public function getFormThemes(): array
    {
        return [
            '@c975LUi/form/block_theme.html.twig',
            '@c975LUi/form/icon_picker_theme.html.twig',
            '@c975LUi/form/media_usages_theme.html.twig',
        ];
    }
}
