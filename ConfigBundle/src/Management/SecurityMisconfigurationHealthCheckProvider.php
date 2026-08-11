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
use c975L\ConfigBundle\Service\SecurityProbeClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// The OWASP "security misconfiguration" family, in the only form a health check can honestly answer for: what the deployed site hands to an anonymous visitor. Everything checked here needs the production site to exist - a debug front controller left routable, a file the web server serves that it should not, a session cookie missing its flags. What is checked from the code instead (a vulnerable dependency, OWASP A06) belongs to "composer audit" in the CI, before the push, not to a run against a site already deployed. Companion to SecurityHeadersHealthCheckProvider: same site-wide scope, same single row on the site root
class SecurityMisconfigurationHealthCheckProvider implements HealthCheckProviderInterface
{
    // The dev front controller's tooling, routable on any site deployed with APP_ENV=dev. Path => a string its real content carries, for the same reason as SENSITIVE_FILES below. "/_fragment" is left out: a Symfony site answers it 400 or 403 whether it runs in debug or not, so a 200 there only ever comes from a catch-all route. A path answering 3xx or 4xx is not exposed, which is why redirects are never followed (see SecurityProbeClient)
    private const array DEBUG_PATHS = [
        '/_profiler' => 'Symfony Profiler',
        '/_wdt/latest' => 'sf-toolbar',
    ];

    // Path => a string its real content carries and no page of the site would. A site answering 200 to everything (catch-all route, soft 404) would otherwise be reported as serving all four
    private const array SENSITIVE_FILES = [
        '/.env' => 'APP_ENV',
        '/composer.json' => '"require"',
        '/composer.lock' => '"packages"',
        '/.git/config' => '[core]',
    ];

    // Directories whose listing exposes the site's whole tree - both are outside the document root on a correct install, hence a 404 rather than a listing
    private const array LISTED_DIRECTORIES = ['/vendor/', '/var/'];

    private const array FLAGS = ['secure', 'httponly', 'samesite'];

    // A severity of an issue inside "details", never a HealthCheckResult status: it is what a finding worth naming but not worth acting on gets, so it shows in the summary without turning the row orange forever
    private const string SEVERITY_INFO = 'info';

    public function __construct(
        private readonly SecurityProbeClient $securityProbeClient,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'security-misconfig';
    }

    public function runChecks(): array
    {
        // Same spelling of the root as every other site-wide check, so all of them group on a single dashboard row; the host without its trailing slash is what every probed path is appended to
        $url = $this->siteUrlResolver->siteRoot();
        $siteUrl = $this->siteUrlResolver->siteUrl();
        if (null === $url || null === $siteUrl) {
            return [];
        }

        try {
            $root = $this->securityProbeClient->probe($url);
            $issues = array_merge(
                $this->findDebugExposure($siteUrl, $root),
                $this->findServedFiles($siteUrl),
                $this->findDirectoryListings($siteUrl),
                $this->findCookieIssues($root),
                $this->findBanners($root),
            );
        } catch (\Throwable $e) {
            return [HealthCheckErrorRow::build($this->translator, 'config', $url, null, 'label.health_check_security_misconfig_call_failed', $e->getMessage())];
        }

        return [[
            'url' => $url,
            'label' => null,
            'status' => $this->resolveStatus($issues),
            'summary' => $this->buildSummary($issues),
            'details' => ['issues' => $issues],
        ]];
    }

    // The profiler answers on its own paths, and leaves its token on every response the site serves - either one means the site runs in debug
    private function findDebugExposure(string $siteUrl, array $root): array
    {
        $exposed = [];
        foreach (self::DEBUG_PATHS as $path => $marker) {
            if ($this->isServed($siteUrl . $path, $marker)) {
                $exposed[] = $path;
            }
        }

        $issues = [];
        if ($exposed) {
            $issues[] = $this->issue(HealthCheckResult::STATUS_ERROR, 'label.health_check_security_misconfig_debug_exposed', ['%paths%' => implode(', ', $exposed)]);
        }
        if (isset($root['headers']['x-debug-token'])) {
            $issues[] = $this->issue(HealthCheckResult::STATUS_ERROR, 'label.health_check_security_misconfig_debug_token');
        }

        return $issues;
    }

    private function findServedFiles(string $siteUrl): array
    {
        $served = [];
        foreach (self::SENSITIVE_FILES as $path => $marker) {
            if ($this->isServed($siteUrl . $path, $marker)) {
                $served[] = $path;
            }
        }

        return $served ? [$this->issue(HealthCheckResult::STATUS_ERROR, 'label.health_check_security_misconfig_files_served', ['%files%' => implode(', ', $served)])] : [];
    }

    private function findDirectoryListings(string $siteUrl): array
    {
        $listed = array_values(array_filter(
            self::LISTED_DIRECTORIES,
            fn (string $path) => $this->isServed($siteUrl . $path, 'Index of '),
        ));

        return $listed ? [$this->issue(HealthCheckResult::STATUS_WARNING, 'label.health_check_security_misconfig_directory_listing', ['%paths%' => implode(', ', $listed)])] : [];
    }

    // A 200 alone proves nothing on a site with a catch-all route, and neither does a content type: a real directory listing is html, exactly like the page a soft 404 would answer with. Recognising the content is the only evidence kept, every marker probed for sitting in the first bytes of the file or listing that carries it
    private function isServed(string $url, string $marker): bool
    {
        try {
            $response = $this->securityProbeClient->probe($url);
        } catch (\Throwable) {
            return false;
        }

        if (200 !== $response['status']) {
            return false;
        }

        return str_contains($response['body'], $marker);
    }

    // Read on the site root's own response: a session cookie is only set once a session starts, so a site setting none here says nothing and is reported as nothing
    private function findCookieIssues(array $root): array
    {
        $issues = [];

        foreach ($root['headers']['set-cookie'] ?? [] as $cookie) {
            $name = trim(explode('=', $cookie, 2)[0]);
            if (!str_contains(strtolower($name), 'sess')) {
                continue;
            }

            $missing = array_values(array_filter(
                self::FLAGS,
                fn (string $flag) => !str_contains(strtolower($cookie), $flag),
            ));

            if ($missing) {
                $issues[] = $this->issue(HealthCheckResult::STATUS_WARNING, 'label.health_check_security_misconfig_cookie_flags', ['%name%' => $name, '%flags%' => implode(', ', $missing)]);
            }
        }

        return $issues;
    }

    // "X-Powered-By" is the site's own to remove (expose_php, header_remove), hence a warning; "Server" belongs to a managed host that rarely lets it be touched, so it is reported without weighing on the status
    private function findBanners(array $root): array
    {
        $issues = [];
        $poweredBy = $root['headers']['x-powered-by'][0] ?? null;
        $server = $root['headers']['server'][0] ?? null;

        if (null !== $poweredBy) {
            $issues[] = $this->issue(HealthCheckResult::STATUS_WARNING, 'label.health_check_security_misconfig_powered_by', ['%value%' => $poweredBy]);
        }
        if (null !== $server && preg_match('/\d/', $server)) {
            $issues[] = $this->issue(self::SEVERITY_INFO, 'label.health_check_security_misconfig_server_banner', ['%value%' => $server]);
        }

        return $issues;
    }

    private function issue(string $severity, string $translationId, array $parameters = []): array
    {
        return [
            'severity' => $severity,
            'message' => $this->translator->trans($translationId, $parameters, 'config'),
        ];
    }

    private function resolveStatus(array $issues): string
    {
        $severities = array_column($issues, 'severity');

        return match (true) {
            \in_array(HealthCheckResult::STATUS_ERROR, $severities, true) => HealthCheckResult::STATUS_ERROR,
            \in_array(HealthCheckResult::STATUS_WARNING, $severities, true) => HealthCheckResult::STATUS_WARNING,
            default => HealthCheckResult::STATUS_OK,
        };
    }

    private function buildSummary(array $issues): string
    {
        $paths = 1 + \count(self::DEBUG_PATHS) + \count(self::SENSITIVE_FILES) + \count(self::LISTED_DIRECTORIES);
        $summary = $this->translator->trans('label.health_check_summary_security_misconfig', ['%paths%' => $paths], 'config');
        $summary .= ' · ' . $this->translator->trans('label.health_check_security_misconfig_scope', [], 'config');

        foreach ($issues as $issue) {
            $summary .= ' · ' . $issue['message'];
        }

        return $summary;
    }
}
