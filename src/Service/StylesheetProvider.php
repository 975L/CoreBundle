<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\BundleStylesheetManagementProviderInterface;
use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;

class StylesheetProvider implements BundleStylesheetProviderInterface, BundleStylesheetManagementProviderInterface
{
    public function getStylesheets(): array
    {
        return [
            'bundles/c975lui/css/animations.min.css',
            'bundles/c975lui/css/styles.min.css',
        ];
    }

    public function getManagementStylesheets(): array
    {
        return [
            'bundles/c975lui/css/management.min.css',
        ];
    }
}
