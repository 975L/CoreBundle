<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implemented by a HealthCheckProvider whose run enumerates the whole of its domain, so a url it no longer returns has nothing left to check - HealthCheckRunner then deletes that kind's rows for the urls missing from the run. What it exists for is that results are kept per (url, kind) and the retention purge deliberately preserves the last row of each pair (see HealthCheckRetentionPurger): a url that can never come back would otherwise keep its last ERROR as the dashboard's current state for good. That is what happens whenever the url carries a generated filename - re-uploading a missing file names it anew (see UiBundle's UiMediaNamer, which appends a fresh uniqid()), so the green row lands on a new url and the red one is orphaned rather than replaced.
//
// Only implement it on a provider that really does list its whole domain every run: an empty run is taken at face value and clears the kind entirely. A provider checking a fixed set of urls (security headers, the certificate, robots.txt) has no reason to - its urls never disappear, and its history is the point.
interface HealthCheckExhaustiveInterface extends HealthCheckProviderInterface
{
}
