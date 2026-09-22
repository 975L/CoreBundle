<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;

// The commands this bundle needs run on a cadence, declared here rather than listed by every site in its own MaintenanceSchedule
class UiMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // Site search index, nightly once the sitemaps are written (0-2): it reads the urls they declare. A no-op on a site whose search isn't configured
            new MaintenanceTask('# #(3-5) * * *', 'c975l:ui:ai-search:index'),
        ];
    }
}
