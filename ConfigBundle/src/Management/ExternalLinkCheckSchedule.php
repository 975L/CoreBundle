<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use Symfony\Component\Clock\ClockInterface;

// Decides whether a content-quality run re-checks the external links of the pages it is about to analyze, or reports back what the last run that did found (see ContentQualityAnalyzer). Internal links are this site's own server and are checked every run; external ones are somebody else's, and a site linking mostly to two or three merchants would otherwise hammer them every week from a single IP - which is how a server ends up rate limited, then blocked
class ExternalLinkCheckSchedule
{
    // How long a set of external verdicts is reported back before the links are called again. Monthly, deliberately far below the weekly cadence the pages themselves are checked at: an external link that dies is not yours to fix on your own schedule, and a month-old verdict is worth more than a blocked IP. A constant rather than a setting - nothing about a site makes another number right, and one is enough until a site says otherwise
    public const int INTERVAL_DAYS = 30;

    // The key holding, in each row's details, when its external links were last really called - written by every run, whether it called them or reported the previous ones back, so the next run reads one date rather than a history
    public const string CHECKED_AT_KEY = 'externalLinksCheckedAt';

    public function __construct(
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    // What the run has to do with the external links of the given page urls: 'due' says whether to call them, 'checkedAt' is the date its rows must carry (now when they are called, the previous run's date when they are not - dropping it would have the next run call them again, and monthly would quietly become weekly), and 'broken' carries back what the last real pass found, keyed by link url, so a dead external link stays reported in between two passes
    // @return array{due: bool, checkedAt: string, broken: array<string, bool>}
    public function decide(array $urls): array
    {
        $checkedAt = null;
        $broken = [];

        // Every kind that checked these urls, not one row per url: only the rows of a check that really called the links carry the date, and another provider's row on the same url would otherwise hide it
        foreach ($this->healthCheckResultRepository->findLatestPerUrlAndKindIn($urls) as $result) {
            $details = $result->getDetails() ?? [];
            $rowCheckedAt = $details[self::CHECKED_AT_KEY] ?? null;
            if (!\is_string($rowCheckedAt)) {
                continue;
            }

            // The most recent date of the batch decides for the whole batch: the pages of one run are checked together and their dates only ever drift apart by the runs that failed halfway
            $checkedAt = null === $checkedAt ? $rowCheckedAt : max($checkedAt, $rowCheckedAt);

            foreach ($details['brokenExternalLinks'] ?? [] as $link) {
                if (isset($link['url'])) {
                    $broken[$link['url']] = true;
                }
            }
        }

        $now = $this->clock->now();
        $due = null === $checkedAt || $checkedAt < $now->modify('-' . self::INTERVAL_DAYS . ' days')->format(\DATE_ATOM);

        return [
            'due' => $due,
            'checkedAt' => $due ? $now->format(\DATE_ATOM) : $checkedAt,
            // Nothing to carry back on a run that calls the links itself - it produces its own verdicts
            'broken' => $due ? [] : $broken,
        ];
    }
}
