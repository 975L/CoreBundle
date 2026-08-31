<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\AccessibilityClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Answers the RGAA 4.1 criteria a page's own markup can settle, one row per url, over every url the site declares for its sitemaps - its pages, and whatever books, products, photos or campaigns the installed bundles add. Nothing to implement bundle-side, same as DeclaredUrlsHealthCheckProvider: declaring a sitemap is all it takes to be checked.
//
// Eight criteria out of the RGAA's 106, and that is the honest count. The share usually quoted as automatable is measured with a browser engine driving the page; read from the markup alone, contrast, focus, tab order and every judgement of *relevance* are out of reach, and are left unanswered rather than guessed at - a compliance report is worth what its weakest line is worth. What it does answer, it answers with no false positive, and it answers it at every deployment rather than once a year.
//
// Criteria 1.1 (image alternatives), 8.5 (page title) and the <h1> count are deliberately absent: ContentQualityAnalyzer already reports them, and traces the offending image back to the very block holding it (see ContentOffenceLocatorRegistry). A dashboard listing one fix twice teaches its reader to skim it
#[AsHealthCheck(AsHealthCheck::FREQUENCY_MONTHLY)]
class AccessibilityHealthCheckProvider implements HealthCheckProviderInterface
{
    // The version of the reference these criterion numbers belong to - carried in every row's details, since a report read a year later has to say which edition it was judged against
    public const string RGAA_VERSION = '4.1';

    // Urls checked per sitemap, so installing a gallery declaring two thousand photos doesn't turn a monthly check into a two-thousand-page crawl. Pages built by one template fail the same criteria in the same place: what is being looked for is the template's offence, not a count of how many urls repeat it
    public const int MAX_URLS_PER_SOURCE = 50;

    public function __construct(
        private readonly iterable $sitemapProviders,
        private readonly AccessibilityClient $accessibilityClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'accessibility';
    }

    public function runChecks(): array
    {
        // Every request is fired before any response is read, letting the HttpClient transport run them concurrently instead of paying each timeout serially - the same shape as AbstractW3cValidationHealthCheckProvider
        $pending = [];
        foreach ($this->collectUrls() as $url) {
            $pending[$url] = $this->accessibilityClient->request($url);
        }

        $results = [];
        foreach ($pending as $url => $response) {
            $results[] = $this->checkUrl($url, $response);
        }

        return $results;
    }

    // Every url the site declares, deduped: two bundles can legitimately declare the same url, and checking it twice would put two rows carrying the same verdict on the dashboard
    private function collectUrls(): array
    {
        $urls = [];

        foreach ($this->sitemapProviders as $sitemapProvider) {
            $collected = 0;

            foreach ($sitemapProvider->getUrls() as $url) {
                $location = \is_array($url) ? ($url['loc'] ?? null) : null;
                // Taken as mixed on purpose: the implementations are other bundles' code, and one incomplete row has to be skipped rather than take the whole check down
                if (!\is_string($location) || '' === $location) {
                    continue;
                }

                $urls[$location] = true;

                if (++$collected >= self::MAX_URLS_PER_SOURCE) {
                    break;
                }
            }
        }

        return array_keys($urls);
    }

    private function checkUrl(string $url, ResponseInterface $response): array
    {
        try {
            $analysis = $this->accessibilityClient->read($response);
        } catch (\Throwable $e) {
            return HealthCheckErrorRow::build($this->translator, 'config', $url, $this->labelFromUrl($url), 'label.health_check_accessibility_call_failed', $e->getMessage());
        }

        $criteria = $this->evaluate($analysis);
        $failed = array_keys(array_filter($criteria, static fn (array $verdict): bool => HealthCheckResult::STATUS_OK !== $verdict['status']));

        return [
            'url' => $url,
            'label' => $this->labelFromUrl($url),
            'status' => $this->resolveStatus($criteria),
            'summary' => $this->summary($criteria, $failed),
            // The whole verdict table, conforming criteria included: this is what a conformity statement is written from, and "checked and found conforming" is exactly the half a list of offences cannot prove
            'details' => ['rgaa' => self::RGAA_VERSION, 'criteria' => $criteria],
            'editUrl' => null,
        ];
    }

    // Each finding read off the markup against the criterion it answers, in the reference's own order. A criterion with nothing to report is conforming rather than "not applicable": telling a page with no table at all from one whose tables are all correct is a distinction only a human audit needs, and one this check has no way to draw
    private function evaluate(array $analysis): array
    {
        return [
            '2.1' => $this->verdict(HealthCheckResult::STATUS_ERROR, $analysis['framesWithoutTitle']),
            '5.6' => $this->verdict(HealthCheckResult::STATUS_WARNING, [], $analysis['tablesWithoutHeaders']),
            '6.2' => $this->verdict(HealthCheckResult::STATUS_ERROR, $analysis['linksWithoutName']),
            '8.3' => $this->verdict(HealthCheckResult::STATUS_ERROR, [], '' === $analysis['language'] ? 1 : 0),
            // Only asked of a page that declares a language at all - one that declares none already fails 8.3, and reporting both would state one fix twice
            '8.4' => $this->verdict(HealthCheckResult::STATUS_ERROR, '' === $analysis['language'] || $analysis['languageIsWellFormed'] ? [] : [$analysis['language']]),
            '9.1' => $this->verdict(HealthCheckResult::STATUS_ERROR, $analysis['headingJumps']),
            '11.1' => $this->verdict(HealthCheckResult::STATUS_ERROR, $analysis['fieldsWithoutLabel']),
            // A doubt rather than a failure: a landmark is one of the five ways criterion 12.6 accepts, and a page reaching its zones by a skip link instead is just as conforming - which no parser can confirm
            '12.6' => $this->verdict(HealthCheckResult::STATUS_WARNING, [], $analysis['hasMainLandmark'] ? 0 : 1),
        ];
    }

    // One criterion's verdict. $count is passed on its own where there is nothing to name - a missing language attribute, an absent landmark - and derived from the offences otherwise
    private function verdict(string $failedStatus, array $offences, ?int $count = null): array
    {
        $count ??= \count($offences);

        return [
            'status' => 0 === $count ? HealthCheckResult::STATUS_OK : $failedStatus,
            'count' => $count,
            'offences' => $offences,
        ];
    }

    // The row's own status is its worst criterion's: one non-conformity makes the page non-conforming, which is what a conformity rate is counted from
    private function resolveStatus(array $criteria): string
    {
        foreach ([HealthCheckResult::STATUS_ERROR, HealthCheckResult::STATUS_WARNING] as $status) {
            foreach ($criteria as $verdict) {
                if ($status === $verdict['status']) {
                    return $status;
                }
            }
        }

        return HealthCheckResult::STATUS_OK;
    }

    // The criteria are named by their number rather than described: "6.2" is what an auditor, a client's accessibility statement and the reference itself all call it, and spelling out eight questions would make a table unreadable
    private function summary(array $criteria, array $failed): string
    {
        if ([] === $failed) {
            return $this->translator->trans('label.health_check_accessibility_conform', ['%count%' => \count($criteria), '%version%' => self::RGAA_VERSION], 'config');
        }

        return $this->translator->trans('label.health_check_accessibility_offences', [
            '%count%' => \count($failed),
            '%criteria%' => implode(', ', $failed),
            '%version%' => self::RGAA_VERSION,
        ], 'config');
    }

    // The url's own path, which is what tells two rows apart on the dashboard - the host is the same for all of them
    private function labelFromUrl(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH);

        return \is_string($path) && '' !== $path ? $path : $url;
    }
}
