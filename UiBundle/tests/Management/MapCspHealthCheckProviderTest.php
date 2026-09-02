<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SecurityHeadersClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\UiBundle\Management\MapCspHealthCheckProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The row that breaks a silence: a Google map fetched from a host the site's own policy does not name is refused by the browser without a word, and all that stays on screen is the list of places
// What it must not do is speak on a site that never chose Google - the provider is picked in a back-office, and a policy widened for a map nobody asked for is a policy widened for nothing
class MapCspHealthCheckProviderTest extends TestCase
{
    private const string ALLOWED = "default-src 'self'; script-src 'self' https://maps.googleapis.com; img-src 'self' https://maps.googleapis.com https://maps.gstatic.com; connect-src 'self' https://maps.googleapis.com";

    public function testTheKindIsTheOneTheDashboardGroupsOn(): void
    {
        $this->assertSame('map-csp', $this->provider('google', self::ALLOWED)->getKind());
        $this->assertSame(MapCspHealthCheckProvider::KIND, $this->provider('google', self::ALLOWED)->getKind());
    }

    // A site on OpenStreetMap has nothing to name and nothing to be told
    public function testASiteDrawingOverOpenStreetMapIsNotAskedForAnything(): void
    {
        $this->assertSame([], $this->provider('leaflet', "default-src 'self'")->runChecks());
        $this->assertSame([], $this->provider(null, "default-src 'self'")->runChecks(), 'A site that never answered the setting is told to widen its policy.');
    }

    public function testAPolicyNamingEveryHostIsReportedWithoutAlarm(): void
    {
        $check = $this->provider('google', self::ALLOWED)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $check['status']);
        $this->assertSame('label.map_csp_allowed', $check['summary']);
        $this->assertSame([], $check['details']['missing']);
    }

    // The whole point of the row: naming what the browser is waiting for rather than "the map does not work"
    public function testAPolicyNamingNoneOfThemNamesEveryDirectiveThatRefuses(): void
    {
        $check = $this->provider('google', "default-src 'self'")->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $check['status']);
        $this->assertSame('label.map_csp_refused[%directives%=script-src, img-src, connect-src]', $check['summary']);
        $this->assertSame(
            [
                'script-src' => ['https://maps.googleapis.com'],
                'img-src' => ['https://maps.googleapis.com', 'https://maps.gstatic.com'],
                'connect-src' => ['https://maps.googleapis.com'],
            ],
            $check['details']['missing'],
            'The row does not say which origins are missing, so the change to make has to be guessed at.'
        );
    }

    // A directive the policy declares none of its own falls back on "default-src", the way a browser reads it
    public function testADirectiveTheSiteDeclaresNoneOfIsReadFromTheDefault(): void
    {
        $check = $this->provider('google', "default-src 'self' https://maps.googleapis.com https://maps.gstatic.com")->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $check['status'], 'A policy covering everything through its default was reported as refusing.');
    }

    // Half a policy is the case worth catching: the script loads and the tiles never arrive
    public function testAPolicyNamingTheScriptAndNotTheTilesSaysSo(): void
    {
        $check = $this->provider('google', "default-src 'self'; script-src 'self' https://maps.googleapis.com; connect-src https://maps.googleapis.com")->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $check['status']);
        $this->assertSame(['img-src' => ['https://maps.googleapis.com', 'https://maps.gstatic.com']], $check['details']['missing']);
    }

    // A site naming a whole zone rather than a host names that host too
    public function testAPolicyNamingAWholeZoneCoversTheHostsUnderIt(): void
    {
        $check = $this->provider('google', "default-src 'self'; script-src https://*.googleapis.com; img-src https://*.googleapis.com https://*.gstatic.com; connect-src https://*.googleapis.com")->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $check['status'], 'A policy naming "https://*.googleapis.com" was read as refusing "https://maps.googleapis.com".');
    }

    // "strict-dynamic" hands the allowlist over to the nonce, which the loader carries (see assets/js/map.js)
    public function testAScriptSourceLeftToTheNonceIsNotAskedToNameAHost(): void
    {
        $check = $this->provider('google', "default-src 'self'; script-src 'nonce-abc' 'strict-dynamic'; img-src https://maps.googleapis.com https://maps.gstatic.com; connect-src https://maps.googleapis.com")->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $check['status'], 'A policy carrying its scripts on a nonce was told to name a host that nonce already covers.');
    }

    // The missing header is another provider's row to raise, and a site refusing nothing draws its map
    public function testASiteServingNoPolicyIsNotReportedAsRefusing(): void
    {
        $check = $this->provider('google', null)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $check['status']);
        $this->assertSame('label.map_csp_no_policy', $check['summary']);
    }

    // Nothing to check on a site whose own address is not known yet
    public function testASiteWithNoAddressIsNotChecked(): void
    {
        $this->assertSame([], $this->provider('google', self::ALLOWED, null)->runChecks());
    }

    // The row points at the setting it is about, so the dashboard offers the place to change it
    public function testTheRowIsLabelledWithTheSettingItIsAbout(): void
    {
        $check = $this->provider('google', self::ALLOWED)->runChecks()[0];

        $this->assertSame('https://exemple.fr', $check['url']);
        $this->assertSame('label.ui_map_provider', $check['label']);
    }

    private function provider(?string $setting, ?string $policy, ?string $url = 'https://exemple.fr'): MapCspHealthCheckProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($setting);

        $client = $this->createStub(SecurityHeadersClient::class);
        $client->method('fetchHeaders')->willReturn(null === $policy ? [] : ['content-security-policy' => $policy]);

        $resolver = $this->createStub(SiteUrlResolver::class);
        $resolver->method('siteRoot')->willReturn($url);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []): string => [] === $parameters
                ? $id
                : $id . '[' . implode(',', array_map(fn ($k, $v): string => $k . '=' . $v, array_keys($parameters), $parameters)) . ']'
        );

        return new MapCspHealthCheckProvider($configService, $client, $resolver, $translator);
    }
}
