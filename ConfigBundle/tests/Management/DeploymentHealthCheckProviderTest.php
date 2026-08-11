<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\DeploymentHealthCheckProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\DeploymentClient;
use c975L\ConfigBundle\Service\HostResolver;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\ConfigBundle\Service\SslCertificateClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeploymentHealthCheckProviderTest extends TestCase
{
    private const string PROBE_URL = 'https://example.com/c975l-health-check-404-probe';
    private const string VARIANT_URL = 'https://www.example.com/';
    private const string SITE_PAGE = '<html><head><title>Introuvable</title></head><body>Example Site</body></html>';
    private const string DEFAULT_ERROR_PAGE = '<html><head><title>An Error Occurred</title></head><body>Oops!</body></html>';

    private function createConfigService(?string $siteUrl, ?string $siteName = 'Example Site'): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['site-url', $siteUrl],
            ['site-name', $siteName],
        ]);

        return $configService;
    }

    // The https redirect and the host variant both go through fetchWithoutRedirect - they are told apart by the host each one asks for
    private function createClient(array $redirect, array $notFound, array $variant): DeploymentClient
    {
        $client = $this->createStub(DeploymentClient::class);
        $client->method('fetchWithoutRedirect')->willReturnCallback(
            static fn (string $url) => str_contains($url, 'www.') ? $variant : $redirect
        );
        $client->method('fetch')->willReturn($notFound);

        return $client;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    // Nothing resolving is the default, so every test about a variant that answers states only what it answers - a host that does resolve while refusing the connection is a case of its own, declared by the tests below
    private function createHostResolver(bool $resolves = false): HostResolver
    {
        $hostResolver = $this->createStub(HostResolver::class);
        $hostResolver->method('resolves')->willReturn($resolves);

        return $hostResolver;
    }

    // No readable certificate by default: only a test about a certificate has one to declare, and the provider treats an unreadable one as an unexplained refusal rather than as a certificate defect
    private function createSslCertificateClient(?array $names = null): SslCertificateClient
    {
        $client = $this->createStub(SslCertificateClient::class);
        if (null === $names) {
            $client->method('fetchSubjectNames')->willThrowException(new \RuntimeException('TLS connection failed'));

            return $client;
        }

        $client->method('fetchSubjectNames')->willReturn($names);

        return $client;
    }

    private function createProvider(?string $siteUrl, array $redirect = [], array $notFound = [], ?string $siteName = 'Example Site', array $variant = []): DeploymentHealthCheckProvider
    {
        $configService = $this->createConfigService($siteUrl, $siteName);

        return new DeploymentHealthCheckProvider(
            $configService,
            new SiteUrlResolver($configService),
            $this->createClient(
                $redirect + ['statusCode' => 301, 'location' => 'https://example.com/'],
                $notFound + ['statusCode' => 404, 'content' => self::SITE_PAGE],
                $variant + ['statusCode' => 301, 'location' => 'https://example.com/'],
            ),
            $this->createTranslator(),
            $this->createHostResolver(),
            $this->createSslCertificateClient(),
        );
    }

    // The provider a variant that resolves is judged by: the http call fails the way a refused TLS handshake makes it fail, and what the certificate says decides which failure it is
    private function createProviderWithUnreachableVariant(?array $certificateNames): DeploymentHealthCheckProvider
    {
        $configService = $this->createConfigService('https://example.com');
        $client = $this->createStub(DeploymentClient::class);
        $client->method('fetchWithoutRedirect')->willReturnCallback(
            static function (string $url) {
                if (str_contains($url, 'www.')) {
                    throw new \RuntimeException('SSL: certificate verify failed');
                }

                return ['statusCode' => 301, 'location' => 'https://example.com/'];
            }
        );
        $client->method('fetch')->willReturn(['statusCode' => 404, 'content' => self::SITE_PAGE]);

        return new DeploymentHealthCheckProvider(
            $configService,
            new SiteUrlResolver($configService),
            $client,
            $this->createTranslator(),
            $this->createHostResolver(true),
            $this->createSslCertificateClient($certificateNames),
        );
    }

    public function testGetKindReturnsDeployment(): void
    {
        $this->assertSame('deployment', $this->createProvider(null)->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $this->assertSame([], $this->createProvider(null)->runChecks());
    }

    public function testRunChecksReturnsOneRowPerCheckWhenAllAreFine(): void
    {
        $results = $this->createProvider('https://example.com')->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame('http://example.com/', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
        $this->assertSame(self::PROBE_URL, $results[1]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
        $this->assertSame(self::VARIANT_URL, $results[2]['url']);
    }

    // A trailing slash on site-url must not produce a double-slashed probe url
    public function testRunChecksBuildsTheProbeUrlFromASiteUrlWithATrailingSlash(): void
    {
        $results = $this->createProvider('https://example.com/')->runChecks();

        $this->assertSame(self::PROBE_URL, $results[1]['url']);
    }

    // Nothing to redirect to on a site not served over https - the 404 and host variant rows remain, the latter asking the http host the site declares
    public function testRunChecksSkipsTheHttpsRedirectOnAnHttpSite(): void
    {
        $results = $this->createProvider('http://example.com')->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame('http://example.com/c975l-health-check-404-probe', $results[0]['url']);
        $this->assertSame('http://www.example.com/', $results[1]['url']);
    }

    public function testRunChecksStatusIsErrorWhenHttpDoesNotRedirect(): void
    {
        $results = $this->createProvider('https://example.com', ['statusCode' => 200, 'location' => null])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame('https-redirect', $results[0]['details']['issue']);
        $this->assertSame(200, $results[0]['details']['statusCode']);
    }

    // A relative Location keeps the visitor on http just as much as an explicit http:// target does
    public function testRunChecksStatusIsWarningWhenTheRedirectStaysOnHttp(): void
    {
        $results = $this->createProvider('https://example.com', ['statusCode' => 301, 'location' => '/'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
        $this->assertSame('insecure-redirect', $results[0]['details']['issue']);
    }

    public function testRunChecksAcceptsAPermanentRedirectToHttps(): void
    {
        $results = $this->createProvider('https://example.com', ['statusCode' => 308, 'location' => 'HTTPS://example.com/'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
    }

    // A soft 404 has search engines index every typo as a page of its own
    public function testRunChecksStatusIsErrorWhenAnUnknownUrlAnswers200(): void
    {
        $results = $this->createProvider('https://example.com', notFound: ['statusCode' => 200, 'content' => self::SITE_PAGE])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[1]['status']);
        $this->assertSame('not-404', $results[1]['details']['issue']);
        $this->assertSame(200, $results[1]['details']['statusCode']);
    }

    public function testRunChecksStatusIsWarningWhenThe404PageIsTheFrameworkDefaultOne(): void
    {
        $results = $this->createProvider('https://example.com', notFound: ['statusCode' => 404, 'content' => self::DEFAULT_ERROR_PAGE])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[1]['status']);
        $this->assertSame('default-404', $results[1]['details']['issue']);
    }

    // The site name is matched case-insensitively - a footer shouting it in uppercase is still the site's own page
    public function testRunChecksMatchesTheSiteNameRegardlessOfCase(): void
    {
        $results = $this->createProvider('https://example.com', notFound: ['statusCode' => 404, 'content' => '<html><body>EXAMPLE SITE</body></html>'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
    }

    // With no site name to match against, a real 404 is taken at face value rather than warned about on a guess
    public function testRunChecksAcceptsAny404WithoutASiteName(): void
    {
        $results = $this->createProvider('https://example.com', notFound: ['statusCode' => 404, 'content' => self::DEFAULT_ERROR_PAGE], siteName: null)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
    }

    // Both hosts serving the site leaves a search engine with two sites carrying the same pages
    public function testRunChecksStatusIsErrorWhenTheOtherHostServesTheSiteToo(): void
    {
        $results = $this->createProvider('https://example.com', variant: ['statusCode' => 200, 'location' => null])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
        $this->assertSame('host-variant', $results[2]['details']['issue']);
        $this->assertSame(200, $results[2]['details']['statusCode']);
    }

    // A redirect that lands anywhere but on the site's own host settles nothing - a relative Location keeps the visitor on the variant host itself
    public function testRunChecksStatusIsWarningWhenTheOtherHostRedirectsElsewhere(): void
    {
        $results = $this->createProvider('https://example.com', variant: ['statusCode' => 301, 'location' => '/'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[2]['status']);
        $this->assertSame('host-variant-redirect', $results[2]['details']['issue']);
    }

    // A 404 from a catch-all vhost duplicates no page, whatever else it says about the server
    public function testRunChecksAcceptsAnOtherHostServingNoContent(): void
    {
        $results = $this->createProvider('https://example.com', variant: ['statusCode' => 404, 'location' => null])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
    }

    // The variant of a site declared with www is its apex, not a second www
    public function testRunChecksProbesTheApexWhenTheSiteIsDeclaredWithWww(): void
    {
        $configService = $this->createConfigService('https://www.example.com');
        $client = $this->createStub(DeploymentClient::class);
        $client->method('fetchWithoutRedirect')->willReturn(['statusCode' => 301, 'location' => 'https://www.example.com/']);
        $client->method('fetch')->willReturn(['statusCode' => 404, 'content' => self::SITE_PAGE]);

        $results = new DeploymentHealthCheckProvider($configService, new SiteUrlResolver($configService), $client, $this->createTranslator(), $this->createHostResolver(), $this->createSslCertificateClient())->runChecks();

        $this->assertSame('https://example.com/', $results[2]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(DeploymentClient::class);
        $client->method('fetchWithoutRedirect')->willThrowException(new \RuntimeException('Connection refused'));
        $client->method('fetch')->willThrowException(new \RuntimeException('Connection refused'));

        $configService = $this->createConfigService('https://example.com');
        $provider = new DeploymentHealthCheckProvider($configService, new SiteUrlResolver($configService), $client, $this->createTranslator(), $this->createHostResolver(), $this->createSslCertificateClient());
        $results = $provider->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame(['error' => 'Connection refused'], $results[0]['details']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[1]['status']);
        // The one check here whose failed call is a pass, and only because nothing resolves under the other host: there is no second site to deduplicate
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
    }

    // The case that used to be reported as a pass: the variant resolves, so something is served there, and the connection is refused anyway. A crawler gets no robots.txt out of it and treats that as a refusal to crawl the whole host
    public function testRunChecksStatusIsErrorWhenAResolvingVariantRefusesTheConnection(): void
    {
        $results = $this->createProviderWithUnreachableVariant(['example.com'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
        $this->assertSame('host-variant-certificate', $results[2]['details']['issue']);
        $this->assertSame(['example.com'], $results[2]['details']['certificateNames']);
    }

    // The shape a shared host answers with when the alias was never added to the certificate: the hosting provider's own, naming the site nowhere. Every name is kept, since naming what the certificate does cover is what turns a failed connection into "reissue it for both spellings of the host"
    public function testRunChecksReportsEveryCertificateNameWhenTheyAllExcludeTheVariant(): void
    {
        $results = $this->createProviderWithUnreachableVariant(['preview.infomaniak.website', '*.infomaniak.site'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
        $this->assertSame('host-variant-certificate', $results[2]['details']['issue']);
        $this->assertSame(['preview.infomaniak.website', '*.infomaniak.site'], $results[2]['details']['certificateNames']);
    }

    // A wildcard covering the variant leaves the refusal unexplained, and blaming the certificate would send the fix in the wrong direction
    public function testRunChecksDoesNotBlameACertificateCoveringTheVariantThroughAWildcard(): void
    {
        $results = $this->createProviderWithUnreachableVariant(['example.com', '*.example.com'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
        $this->assertSame('host-variant-unreachable', $results[2]['details']['issue']);
    }

    // An unreadable certificate costs the explanation, never the row
    public function testRunChecksStatusIsErrorWhenAResolvingVariantPresentsNoReadableCertificate(): void
    {
        $results = $this->createProviderWithUnreachableVariant(null)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
        $this->assertSame('host-variant-unreachable', $results[2]['details']['issue']);
        $this->assertSame('SSL: certificate verify failed', $results[2]['details']['error']);
    }
}
