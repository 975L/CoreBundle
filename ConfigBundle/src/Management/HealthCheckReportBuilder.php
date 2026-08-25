<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;

// Builds the diagnostic report an admin downloads off the "Health check" page to hand over to whoever fixes the site - a developer, a support desk, an AI assistant. The dashboard says what is wrong on the site; this says it in a form something else can read, which is what a screenshot of a table never is.
//
// It is the status report (see StatusReportBuilder: what this site runs, and what its checks last found) plus the very same run's rows carrying the "details" that report deliberately leaves out - the checkers' raw payloads, which is what makes a row actionable. Those stay out of /status/report because that payload leaves the site over the network; here the file is served to an authenticated admin who asked for it, and the whole point is the details. Whoever reads it can therefore stop at "checks" and get exactly what a console would.
//
// Read-only and side-effect free, so it can be asked for as often as wanted.
class HealthCheckReportBuilder
{
    // Bumped whenever the payload's shape changes in a way a reader has to care about
    public const VERSION = 1;

    // The rows worth carrying: an "ok" row says nothing to fix, and a "skipped" one says the check never ran. Both still show in the counts below, so a reader sees what was checked rather than reading this list as the whole run
    private const array REPORTED_STATUSES = [HealthCheckResult::STATUS_ERROR, HealthCheckResult::STATUS_WARNING];

    public function __construct(
        private readonly StatusReportBuilder $statusReportBuilder,
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
    ) {
    }

    // The whole report, as a json-serializable array
    public function build(): array
    {
        return [
            'reportVersion' => self::VERSION,
            ...$this->statusReportBuilder->build(),
            'results' => $this->getResults(),
        ];
    }

    // Every row that needs acting on, details included. Nothing is capped, unlike the status report's own issue list: that one travels over the network and has to stay sized by the site's state rather than by its content, where this one is a file an admin downloads - a report cut short is a report whose reader fixes what it happened to show
    private function getResults(): array
    {
        $results = [];

        foreach ($this->healthCheckResultRepository->findLatestPerUrlAndKind() as $row) {
            if (!\in_array($row->getStatus(), self::REPORTED_STATUSES, true)) {
                continue;
            }

            $results[] = [
                'kind' => $row->getKind(),
                'url' => $row->getUrl(),
                'label' => $row->getLabel(),
                'status' => $row->getStatus(),
                'summary' => $row->getSummary(),
                'checkedAt' => $row->getCheckedAt()->format(\DateTimeInterface::ATOM),
                // Carried rather than filtered out: a row an admin declared dealt with is still a row, and whoever reads this has to know it was seen rather than wonder why it is not there
                'acknowledgedAt' => $row->getAcknowledgedAt()?->format(\DateTimeInterface::ATOM),
                'details' => $row->getDetails(),
            ];
        }

        return $results;
    }
}
