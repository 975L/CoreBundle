<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

interface BundleStylesheetManagementProviderInterface
{
    /**
     * Returns the list of CSS stylesheets to inject in the EasyAdmin management dashboard.
     * Use relative public paths (e.g. "bundles/c975lui/css/management.min.css")
     * or absolute URLs for CDN resources (e.g. "https://...").
     *
     * @return string[]
     */
    public function getManagementStylesheets(): array;
}
