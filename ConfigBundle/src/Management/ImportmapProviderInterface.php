<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implement this to have your bundle's own AssetMapper importmap.php entries (a Stimulus controller shipped in assets/, typically) added automatically to the consuming app - collected by ImportmapRegistry and written by the c975l:config:check-importmap command (wired into composer.json's post-update-cmd), see readme. Split in two methods mirroring UiBundle's BundleScriptAdminProviderInterface/BundleScriptProviderInterface admin/non-admin distinction, so each entry's purpose stays explicit at the declaration site - both are merged into the same importmap.php by ImportmapRegistry, the split only matters to the reader.
interface ImportmapProviderInterface
{
    /**
     * Entries for scripts loaded on the /management dashboard only (typically also returned by BundleScriptAdminProviderInterface::getAdminScripts()). 'path' is relative to the declaring bundle's own directory (e.g. 'assets/controllers-admin.js'), ImportmapRegistry prefixing it with where that bundle sits under vendor/ - a bundle never spells that out itself, its package being free to ship several bundles. A provider shipped by the application itself gets no prefix: its paths are the project root's own, exactly as they appear in importmap.php. Return [] if none.
     *
     * @return array<string, array{path: string, entrypoint?: bool}> import name (e.g. '@c975l/my-bundle/controllers-admin.js') => entry
     */
    public function getAdminImportmapEntries(): array;

    /**
     * Entries for scripts used elsewhere - the site's front-end (typically also returned by BundleScriptProviderInterface::getScripts()) or any other AssetMapper dependency needing an importmap.php entry. Same shape as getAdminImportmapEntries(). Return [] if none.
     *
     * @return array<string, array{path: string, entrypoint?: bool}> import name => entry
     */
    public function getImportmapEntries(): array;
}
