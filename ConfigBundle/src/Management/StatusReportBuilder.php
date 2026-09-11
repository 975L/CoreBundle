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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Kernel;

// Builds the report the /status/report route serves and the c975l:status:dump command prints: what this site runs, and what its health checks last found. Read-only and side-effect free, so it can be asked for as often as wanted - which is what lets a console ask rather than wait to be told. Everything it collects is already known to the site - it aggregates, it never checks anything itself (see HealthCheckProviderInterface for that)
class StatusReportBuilder
{
    // Bumped whenever the payload's shape changes in a way a receiver has to care about, so a console can keep reading older sites while they are being updated
    public const VERSION = 2;

    // Caps the error rows carried by one report. A site with hundreds of broken pages would otherwise send a payload sized by its content rather than by its state - the counts stay exact, only the list is cut, and issuesTruncated says so rather than letting a receiver read a short list as "that's all of them"
    private const int MAX_ISSUES = 20;

    // Checker messages carried per error row: five say what to fix, the rest being more of the same on a page whoever fixes it is about to open anyway
    private const int MAX_ERRORS_PER_ISSUE = 5;

    // A validator names the cause in its first words: a longer message is cut rather than dropped, the same figure PageSpeedInsightsClient keeps of Google's
    private const int MAX_ERROR_LENGTH = 200;

    public function __construct(
        private readonly iterable $statusProviders,
        private readonly ConfigServiceInterface $configService,
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    // The whole report, as a json-serializable array
    public function build(): array
    {
        return [
            'version' => self::VERSION,
            'site' => (string) $this->configService->get('site-url'),
            'generatedAt' => new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
            'environment' => $this->environment,
            'php' => \PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'packages' => $this->getPackages(),
            'dependencies' => $this->getDependencies(),
            'checks' => $this->getChecks(),
            'extra' => $this->getExtra(),
        ];
    }

    // The bundles this site runs, and their versions. Bundles rather than dependencies: listing what the application requires meant a few dozen lines where "symfony/mime" sat next to EasyAdmin, and no maintainer ever compares those across sites. Whether a bundle is a direct requirement or came with another one doesn't matter here - what is installed is what runs
    private function getPackages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getInstalledPackagesByType('symfony-bundle') as $name) {
            // The framework's own bundles all carry the Symfony version, already reported as its own field
            if (str_starts_with($name, 'symfony/')) {
                continue;
            }

            $packages[$name] = InstalledVersions::getPrettyVersion($name);
        }

        ksort($packages);

        return $packages;
    }

    // Everything installed, name and version, for a receiver to look up against a vulnerability database - which is the one question "packages" above cannot answer: a CVE lands just as often on dompdf, Doctrine or Twig as on a bundle, and none of those is a symfony-bundle. Sent rather than checked here on purpose, and the report stays as side-effect free as its comment promises: a console holding thirty sites' lists resolves them all in a single call to the advisory API, where thirty sites checking themselves would mean thirty sites making a network call on a schedule to learn what one lookup already knows. It also stays true to what the checks are for (see SecurityMisconfigurationHealthCheckProvider: a vulnerable dependency is answered from the code, not from a run against a deployed site) - the difference being that a console asks every day, and "composer audit" in the CI only answers on the days someone pushes.
    //
    // Platform entries (php, ext-*, composer-*) are left out, having no slash and no advisory to match, and PHP's own version is already its own field. So are the replaced and provided packages, which carry no version to compare
    private function getDependencies(): array
    {
        $dependencies = [];

        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if (!str_contains($name, '/')) {
                continue;
            }

            $version = InstalledVersions::getPrettyVersion($name);
            if (null === $version) {
                continue;
            }

            $dependencies[$name] = $version;
        }

        ksort($dependencies);

        return $dependencies;
    }

    // What the last health check run found, as counts plus the rows in error. HealthCheckResult::$details stays behind: it holds the checkers' raw payloads, big and occasionally revealing. Only its "errors" list travels, capped - a validator's sentences about a public page, neither big nor secret, and what turns a mailed row into something fixable from the mail rather than a count sending its reader to the site
    private function getChecks(): ?array
    {
        try {
            $rows = $this->healthCheckResultRepository->findLatestPerUrlAndKind();
        } catch (\Throwable) {
            // A site with the bundle installed but its migrations not run yet still has a version and a package list worth reporting - the absence of the section says the checks are unavailable, which is not the same as "no issue found"
            return null;
        }

        $counts = array_fill_keys(HealthCheckResult::STATUSES, 0);
        $issues = [];
        $lastRunAt = null;

        foreach ($rows as $row) {
            ++$counts[$row->getStatus()];

            if (null === $lastRunAt || $row->getCheckedAt() > $lastRunAt) {
                $lastRunAt = $row->getCheckedAt();
            }

            // Errors only: a site in warning is a site to improve, and its own dashboard already lists where. What has to travel is what needs acting on today - the warning count still shows in "counts", so a site drifting is visible without carrying every row it drifted on
            if (HealthCheckResult::STATUS_ERROR === $row->getStatus()) {
                $issues[] = [
                    'kind' => $row->getKind(),
                    'url' => $row->getUrl(),
                    'summary' => $row->getSummary(),
                    'errors' => $this->getErrors($row),
                ];
            }
        }

        return [
            'counts' => $counts,
            'lastRunAt' => $lastRunAt?->format(\DateTimeInterface::ATOM),
            'issues' => \array_slice($issues, 0, self::MAX_ISSUES),
            'issuesTruncated' => \count($issues) > self::MAX_ISSUES,
        ];
    }

    // The checker's own messages when it lists them under "errors", as the W3C checks do - strings only, capped in number and in length, never the rest of the payload. An added key: a receiver reading a site not updated yet simply finds none
    private function getErrors(HealthCheckResult $row): array
    {
        $errors = array_values(array_filter((array) (($row->getDetails() ?? [])['errors'] ?? []), is_string(...)));

        return array_map(
            static fn (string $error): string => mb_substr($error, 0, self::MAX_ERROR_LENGTH),
            \array_slice($errors, 0, self::MAX_ERRORS_PER_ISSUE),
        );
    }

    // What the installed bundles chose to add, one section per provider (see StatusProviderInterface). A provider that throws must not cost the whole report: the site would then look silent to a receiver, which reads as a much worse problem than the one section that failed
    private function getExtra(): \stdClass
    {
        $extra = [];

        foreach ($this->statusProviders as $provider) {
            // Resolved first so the catch never has to call getStatusKey() again: were that the throwing call, naming it a second time would rethrow past the catch and cost the whole report the comment above promises to keep
            $key = $provider::class;

            try {
                $key = $provider->getStatusKey();
                $extra[$key] = $provider->getStatusData();
            } catch (\Throwable $e) {
                $extra[$key] = ['error' => $e->getMessage()];
            }
        }

        // Returned as an object rather than an array: a site with no provider at all - the common case - would otherwise send "extra": [], and a receiver reading it as a keyed structure breaks on the one payload shape it will meet most often
        return (object) $extra;
    }
}
