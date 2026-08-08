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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\DeploymentClient;
use c975L\ConfigBundle\Service\HostResolver;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\ConfigBundle\Service\SslCertificateClient;
use Symfony\Contracts\Translation\TranslatorInterface;

// Three site-wide deployment checks nothing else covers, all silent when they break: that http:// actually redirects to https:// (a vhost/proxy setting no page-level check can see - the site itself answers perfectly well over https meanwhile), that an unknown url answers a real 404 carrying the site's own error page (a soft 404 answering 200 has search engines index every typo as a page), and that the site isn't served a second time under the other spelling of its host (www vs apex). All three are asked of urls nobody visits - which is precisely why nothing else reports them
class DeploymentHealthCheckProvider implements HealthCheckProviderInterface
{
    // Deliberately a fixed url rather than a random one: the same path is probed on every run, so it reads as this check in the access logs instead of as a rotating stream of unexplained 404s
    private const NOT_FOUND_PROBE_PATH = '/c975l-health-check-404-probe';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly DeploymentClient $deploymentClient,
        private readonly TranslatorInterface $translator,
        private readonly HostResolver $hostResolver,
        private readonly SslCertificateClient $sslCertificateClient,
    ) {
    }

    public function getKind(): string
    {
        return 'deployment';
    }

    public function runChecks(): array
    {
        $siteUrl = $this->siteUrlResolver->siteUrl();
        if (null === $siteUrl) {
            return [];
        }

        $rows = [];
        // Nothing to redirect *to* on a site not served over https in the first place - that's SslCertificateHealthCheckProvider's territory, and it skips itself the same way
        if (str_starts_with($siteUrl, 'https://')) {
            $rows[] = $this->checkHttpsRedirect($siteUrl);
        }

        $rows[] = $this->checkNotFoundPage($siteUrl);

        return array_merge($rows, $this->checkHostVariant($siteUrl));
    }

    // The same site served a second time under the other spelling of its host - "www.example.com" when "site-url" declares "example.com", and the reverse. Two hosts answering the same pages are two sites to a search engine: each page exists twice, and the links and the history it earned are split between the two instead of adding up. Nothing on the site shows it, since the host that has to be asked is the one nobody ever uses. A redirect settles it, whichever of the two is kept, as long as it lands on the host "site-url" declares
    private function checkHostVariant(string $siteUrl): array
    {
        $host = parse_url($siteUrl, \PHP_URL_HOST);
        if (!\is_string($host) || '' === $host) {
            return [];
        }

        $variantHost = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
        $url = str_replace('://' . $host, '://' . $variantHost, $siteUrl) . '/';
        $label = $this->translator->trans('label.health_check_deployment_host_variant', [], 'config');

        try {
            $response = $this->deploymentClient->fetchWithoutRedirect($url);
        } catch (\Throwable $e) {
            return [$this->judgeUnreachableVariant($url, $label, $variantHost, $e)];
        }

        return [$this->judgeHostVariant($url, $label, $variantHost, $host, $response)];
    }

    // A call that never completed says two opposite things, and used to be read as only one of them. Nothing resolving means nothing is served under that spelling, which is the pass this check looks for - but a host that does resolve and still refuses the connection is the worst of the three cases, and was being reported as that same pass. A crawler asking it for robots.txt gets no answer either, and an unreadable robots.txt is not taken as "allowed": Googlebot treats it as a blanket refusal and leaves the whole host alone, which Search Console then reports as "Blocked by robots.txt" on urls the site never blocked anywhere
    private function judgeUnreachableVariant(string $url, string $label, string $variantHost, \Throwable $exception): array
    {
        if (!$this->hostResolver->resolves($variantHost)) {
            return $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_host_variant_absent', ['%host%' => $variantHost]);
        }

        // Naming what the certificate does cover is what turns "the connection failed" into the one action to take - reissuing it for both spellings of the host. A certificate that covers this host fine leaves the refusal unexplained, and saying so plainly beats blaming the wrong thing
        $names = $this->certificateNames($variantHost);

        return [] !== $names && !$this->certificateCovers($names, $variantHost)
            ? $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_host_variant_certificate', ['%host%' => $variantHost, '%names%' => implode(', ', $names)], ['issue' => 'host-variant-certificate', 'certificateNames' => $names])
            : $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_host_variant_unreachable', ['%host%' => $variantHost, '%message%' => $exception->getMessage()], ['issue' => 'host-variant-unreachable', 'error' => $exception->getMessage()]);
    }

    // The hostnames the certificate this variant presents is valid for, or none at all when it presents nothing readable - the check is already reporting a failure either way, so an unreadable certificate only costs the explanation, never the row
    // @return list<string>
    private function certificateNames(string $host): array
    {
        try {
            return $this->sslCertificateClient->fetchSubjectNames($host);
        } catch (\Throwable) {
            return [];
        }
    }

    // Whether any name on the certificate covers this host, wildcards included - "*.example.com" covers "www.example.com" but never the apex itself, which is the asymmetry that leaves a "www" alias uncovered by a certificate issued for the apex alone (Let's Encrypt issues exactly what it was asked for, and adding the alias to the hosting panel doesn't reissue it)
    // @param list<string> $names
    private function certificateCovers(array $names, string $host): bool
    {
        $host = strtolower($host);
        $parent = str_contains($host, '.') ? substr($host, strpos($host, '.') + 1) : null;

        foreach ($names as $name) {
            if ($name === $host || (null !== $parent && str_starts_with($name, '*.') && substr($name, 2) === $parent)) {
                return true;
            }
        }

        return false;
    }

    private function judgeHostVariant(string $url, string $label, string $variantHost, string $host, array $response): array
    {
        $status = $response['statusCode'];

        if ($status >= 300 && $status < 400) {
            // A relative Location ("/") redirects within the variant host itself, leaving both hosts serving the site just as much as no redirect at all - only the host it lands on settles which of the two is the site
            return parse_url((string) $response['location'], \PHP_URL_HOST) === $host
                ? $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_host_variant_redirect_ok', ['%host%' => $variantHost])
                : $this->row($url, $label, HealthCheckResult::STATUS_WARNING, 'label.health_check_host_variant_redirect_elsewhere', ['%url%' => (string) $response['location']], ['issue' => 'host-variant-redirect', 'location' => $response['location']]);
        }

        // Answering anything but content - a 404 from a catch-all vhost, a 500 - leaves no page duplicated, whatever else it says about the server
        if ($status >= 400) {
            return $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_host_variant_absent', ['%host%' => $variantHost]);
        }

        return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_host_variant_duplicate', ['%host%' => $variantHost], ['issue' => 'host-variant', 'statusCode' => $status]);
    }

    private function checkHttpsRedirect(string $siteUrl): array
    {
        $label = $this->translator->trans('label.health_check_deployment_https', [], 'config');
        $url = 'http://' . substr($siteUrl, \strlen('https://')) . '/';

        try {
            $response = $this->deploymentClient->fetchWithoutRedirect($url);
        } catch (\Throwable $e) {
            return $this->errorRow($url, $label, $e->getMessage());
        }

        if ($response['statusCode'] < 300 || $response['statusCode'] >= 400) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_https_redirect_missing', ['%status%' => $response['statusCode']], ['issue' => 'https-redirect', 'statusCode' => $response['statusCode']]);
        }

        // A relative Location ("/") redirects within http, keeping the visitor unencrypted just as much as an explicit http:// target does
        if (!str_starts_with(strtolower((string) $response['location']), 'https://')) {
            return $this->row($url, $label, HealthCheckResult::STATUS_WARNING, 'label.health_check_https_redirect_insecure', [], ['issue' => 'insecure-redirect', 'location' => $response['location']]);
        }

        return $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_https_redirect_ok');
    }

    // "Custom" is a heuristic, same spirit as SeoFilesHealthCheckProvider's robots.txt one: an error page rendered inside the site's own layout carries the site name somewhere (header, footer, title), the framework's default one never does. It only ever downgrades an otherwise correct 404 to a warning, and is skipped altogether when no site-name is configured to match against
    private function checkNotFoundPage(string $siteUrl): array
    {
        $label = $this->translator->trans('label.health_check_deployment_not_found', [], 'config');
        $url = $siteUrl . self::NOT_FOUND_PROBE_PATH;

        try {
            $file = $this->deploymentClient->fetch($url);
        } catch (\Throwable $e) {
            return $this->errorRow($url, $label, $e->getMessage());
        }

        if (404 !== $file['statusCode']) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_not_found_wrong_status', ['%status%' => $file['statusCode']], ['issue' => 'not-404', 'statusCode' => $file['statusCode']]);
        }

        $siteName = trim((string) $this->configService->get('site-name'));
        if ('' !== $siteName && !str_contains(mb_strtolower($file['content']), mb_strtolower($siteName))) {
            return $this->row($url, $label, HealthCheckResult::STATUS_WARNING, 'label.health_check_not_found_default_page', [], ['issue' => 'default-404']);
        }

        return $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_not_found_ok');
    }

    private function row(string $url, string $label, string $status, string $translationId, array $params = [], array $details = []): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => $status,
            'summary' => $this->translator->trans($translationId, $params, 'config'),
            'details' => $details,
        ];
    }

    private function errorRow(string $url, string $label, string $message): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => HealthCheckResult::STATUS_ERROR,
            'summary' => $this->translator->trans('label.health_check_deployment_call_failed', ['%message%' => $message], 'config'),
            'details' => ['error' => $message],
        ];
    }
}
