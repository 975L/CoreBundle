<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ImportmapProviderInterface;

// Same import names as ScriptProvider (BundleScriptAdminProviderInterface/BundleScriptProviderInterface) - that one tells the dashboard/front layout which scripts to load, this one tells c975l:config:check-importmap what importmap.php entry each one needs
class ImportmapProvider implements ImportmapProviderInterface
{
    public function getAdminImportmapEntries(): array
    {
        return [
            '@c975l/ui-bundle/controllers-admin.js' => [
                'path' => 'assets/controllers-admin.js',
                'entrypoint' => true,
            ],
            // No entrypoint: nothing loads this one as a <script> of its own, it is imported by name from another bundle's admin controller (see readme, "Reusing the gesture elsewhere"). Declared all the same because a bare specifier with no importmap entry doesn't just fail to resolve - it takes the whole importing module down with it, and every controller that module was going to register with it
            '@c975l/ui-bundle/pointer-sort.js' => [
                'path' => 'assets/js/pointer-sort.js',
            ],
        ];
    }

    public function getImportmapEntries(): array
    {
        return [
            '@c975l/ui-bundle/controllers.js' => [
                'path' => 'assets/controllers.js',
                'entrypoint' => true,
            ],
        ];
    }
}
