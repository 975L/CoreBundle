<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Scheduler;

// The commands this bundle needs run on a cadence, declared here rather than listed by every site in its own MaintenanceSchedule
class ConfigMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // Sitemaps: nightly, once the day's content is in
            new MaintenanceTask('# #(0-2) * * *', 'c975l:sitemaps:create'),
            // robots.txt/humans.txt/llms.txt, on hours that never overlap the sitemaps': robots.txt only declares the sitemap index once that run has written one, and llms.txt lists the same urls - overlapping ranges would let a site's hash draw the two in the wrong order and ship a robots.txt without its Sitemap: line for the day
            new MaintenanceTask('# #(3-5) * * *', 'c975l:seo:files:create'),
            // Failed messenger rows past their retention, nightly, once the backups have had their window
            new MaintenanceTask('# #(2-4) * * *', 'c975l:config:messenger-cleanup'),
            // Backup: every 6 hours (DB dumped table by table, plus the files declared in "archive" mode), the archives being sent offsite in the same run
            new MaintenanceTask('# */6 * * *', 'c975l:config:backup'),
            // The mirrored folders, nightly and on their own: uploads are written once and weigh far more than
            // everything else here, so they don't belong on the 6-hourly cadence. Seed the first run by hand -
            // it transfers the whole lot, and the scheduler has a single worker to block
            new MaintenanceTask('# #(1-3) * * *', 'c975l:config:backup:offsite'),
            // Digest of the week's backups, on its own entry rather than as --report on a backup run: a summary riding on a backup only exists if that run reaches its last line, and no mail at all is what nobody notices
            new MaintenanceTask('# #(2-5) * * 1', 'c975l:config:backup:digest'),
            // Health check: a cadence, never a list of kinds - every provider declares its own with AsHealthCheck (weekly unless it says otherwise), so these two already account for whatever bundles the site installs later
            new MaintenanceTask('# #(3-6) * * 0', 'c975l:health-check:run --frequency=weekly'),
            // The heavy ones apart: a gallery declares one url per photo, by far the longest run. Their day of the month is drawn too, rather than the 1st for every site
            new MaintenanceTask('# #(4-7) # * *', 'c975l:health-check:run --frequency=monthly'),
        ];
    }
}
