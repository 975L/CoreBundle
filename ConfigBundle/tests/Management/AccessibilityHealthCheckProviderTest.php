<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\AccessibilityHealthCheckProvider;
use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Service\AccessibilityClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccessibilityHealthCheckProviderTest extends TestCase
{
    private const string CONFORMING_PAGE = '<html lang="fr"><head><title>Accueil</title></head><body><main><h1>Accueil</h1><a href="/contact">Nous contacter</a></main></body></html>';

    private function createSitemapProvider(array $urls): SitemapProviderInterface
    {
        $provider = $this->createStub(SitemapProviderInterface::class);
        $provider->method('getSitemapName')->willReturn('pages');
        $provider->method('getUrls')->willReturn(array_map(static fn (string $url): array => ['loc' => $url], $urls));

        return $provider;
    }

    // The translated summary is not what is under test here, so it answers its own id back - a row is read by its status and its criteria
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    private function createProvider(array $urls, string | array $html): AccessibilityHealthCheckProvider
    {
        $bodies = \is_array($html) ? $html : array_fill(0, max(1, \count($urls)), $html);
        $httpClient = new MockHttpClient(array_map(static fn (string $body): MockResponse => new MockResponse($body, ['http_code' => 200]), $bodies));

        return new AccessibilityHealthCheckProvider([$this->createSitemapProvider($urls)], new AccessibilityClient($httpClient), $this->createTranslator());
    }

    public function testTheKindAndItsCadenceAreDeclared(): void
    {
        $this->assertSame('accessibility', $this->createProvider([], self::CONFORMING_PAGE)->getKind());

        $attribute = new \ReflectionClass(AccessibilityHealthCheckProvider::class)->getAttributes(AsHealthCheck::class)[0]->newInstance();
        $this->assertSame(AsHealthCheck::FREQUENCY_MONTHLY, $attribute->frequency);
    }

    public function testAConformingPageCarriesEveryCriterionAsMet(): void
    {
        $rows = $this->createProvider(['https://example.com/accueil'], self::CONFORMING_PAGE)->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame('/accueil', $rows[0]['label']);
        $this->assertSame('4.1', $rows[0]['details']['rgaa']);

        foreach ($rows[0]['details']['criteria'] as $verdict) {
            $this->assertSame(HealthCheckResult::STATUS_OK, $verdict['status']);
        }
    }

    // The criteria are listed in the reference's own order, not sorted as strings - "11.1" comes after "9.1", where a string sort would put it before "2.1"
    public function testTheCriteriaAreListedInTheReferenceOrder(): void
    {
        $rows = $this->createProvider(['https://example.com/accueil'], self::CONFORMING_PAGE)->runChecks();

        $this->assertSame(['2.1', '5.6', '6.2', '8.3', '8.4', '9.1', '11.1', '12.6'], array_keys($rows[0]['details']['criteria']));
    }

    public function testAnUnlabelledLinkFailsCriterion62AndTurnsTheRowRed(): void
    {
        $html = '<html lang="fr"><head><title>T</title></head><body><main><a href="/panier"><i class="fa"></i></a></main></body></html>';

        $rows = $this->createProvider(['https://example.com/boutique'], $html)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['details']['criteria']['6.2']['status']);
        $this->assertSame(['<a href="/panier">'], $rows[0]['details']['criteria']['6.2']['offences']);
        $this->assertSame('label.health_check_accessibility_offences', $rows[0]['summary']);
    }

    // A missing landmark is a doubt, not a non-conformity: criterion 12.6 accepts four other ways of reaching a zone
    public function testAMissingMainLandmarkOnlyWarns(): void
    {
        $html = '<html lang="fr"><head><title>T</title></head><body><h1>Accueil</h1></body></html>';

        $rows = $this->createProvider(['https://example.com/accueil'], $html)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['details']['criteria']['12.6']['status']);
    }

    // One fix, one line: a page declaring no language fails 8.3, and 8.4 is not asked of a code that isn't there
    public function testAPageWithoutALanguageFails83Alone(): void
    {
        $html = '<html><head><title>T</title></head><body><main><h1>Accueil</h1></main></body></html>';

        $criteria = $this->createProvider(['https://example.com/accueil'], $html)->runChecks()[0]['details']['criteria'];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $criteria['8.3']['status']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $criteria['8.4']['status']);
    }

    public function testALanguageWrittenOutInFullFails84WithItsOwnValue(): void
    {
        $html = '<html lang="français"><head><title>T</title></head><body><main><h1>Accueil</h1></main></body></html>';

        $criteria = $this->createProvider(['https://example.com/accueil'], $html)->runChecks()[0]['details']['criteria'];

        $this->assertSame(HealthCheckResult::STATUS_OK, $criteria['8.3']['status']);
        $this->assertSame(['français'], $criteria['8.4']['offences']);
    }

    public function testAnUrlDeclaredTwiceIsCheckedOnce(): void
    {
        $provider = $this->createProvider(['https://example.com/accueil', 'https://example.com/accueil'], [self::CONFORMING_PAGE]);

        $this->assertCount(1, $provider->runChecks());
    }

    public function testASitemapEntryWithoutALocationIsSkipped(): void
    {
        $sitemapProvider = $this->createStub(SitemapProviderInterface::class);
        $sitemapProvider->method('getUrls')->willReturn([['lastmod' => '2026-08-01'], ['loc' => 'https://example.com/accueil']]);
        $httpClient = new MockHttpClient([new MockResponse(self::CONFORMING_PAGE, ['http_code' => 200])]);

        $rows = new AccessibilityHealthCheckProvider([$sitemapProvider], new AccessibilityClient($httpClient), $this->createTranslator())->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('https://example.com/accueil', $rows[0]['url']);
    }

    // A gallery declaring two thousand photos would otherwise turn a monthly check into a crawl, for pages that all fail in the same place
    public function testASitemapIsCappedAtTheDeclaredNumberOfUrls(): void
    {
        $urls = array_map(static fn (int $i): string => 'https://example.com/photo/' . $i, range(1, AccessibilityHealthCheckProvider::MAX_URLS_PER_SOURCE + 20));

        $provider = $this->createProvider($urls, array_fill(0, AccessibilityHealthCheckProvider::MAX_URLS_PER_SOURCE, self::CONFORMING_PAGE));

        $this->assertCount(AccessibilityHealthCheckProvider::MAX_URLS_PER_SOURCE, $provider->runChecks());
    }

    public function testAPageThatCannotBeReadIsAnErrorRowAndNotAVerdict(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 500]));

        $provider = new AccessibilityHealthCheckProvider([$this->createSitemapProvider(['https://example.com/accueil'])], new AccessibilityClient($httpClient), $this->createTranslator());
        $rows = $provider->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('label.health_check_accessibility_call_failed', $rows[0]['summary']);
        $this->assertArrayNotHasKey('criteria', $rows[0]['details']);
    }
}
