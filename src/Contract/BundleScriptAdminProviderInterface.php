<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

interface BundleScriptAdminProviderInterface
{
    /**
     * Returns the JS modules to load on the EasyAdmin management dashboard, as AssetMapper import names
     * (e.g. "@c975l/my-bundle/controllers-admin.js"). Each one needs a matching importmap.php entry,
     * usually declared through ConfigBundle's ImportmapProviderInterface::getAdminImportmapEntries().
     *
     * @return string[]
     */
    public function getAdminScripts(): array;
}
