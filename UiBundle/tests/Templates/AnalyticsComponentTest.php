<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// Three ends drift silently here, none of them raising an error: a template no longer guarded measures a site that turned tracking off, an identifier no longer registered leaves the div inert, a provider no longer in the driver map measures nothing
class AnalyticsComponentTest extends TestCase
{
    // The guard belongs to the components rather than to each layout calling them, or a caller forgetting it tracks a site that asked not to be
    public function testBothComponentsCarryTheirOwnGuard(): void
    {
        foreach (['Tracker', 'Order'] as $component) {
            $this->assertStringContainsString("{% set provider = analytics_provider() %}\n{% if provider %}", $this->read(\sprintf('templates/components/Analytics/%s.html.twig', $component)));
        }
    }

    // Lazily registered under the identifiers the components write, the barrel looking the elements up by them
    public function testTheControllersAreRegisteredUnderTheIdentifiersWritten(): void
    {
        $barrel = $this->read('assets/controllers.js');

        $this->assertStringContainsString('data-controller="analytics"', $this->read('templates/components/Analytics/Tracker.html.twig'));
        $this->assertStringContainsString("analytics: () => import('./js/analytics.js')", $barrel);
        $this->assertStringContainsString('data-controller="analytics-order"', $this->read('templates/components/Analytics/Order.html.twig'));
        $this->assertStringContainsString("'analytics-order': () => import('./js/analytics-order.js')", $barrel);
    }

    // Every provider choice has a driver, or a site choosing it measures nothing
    public function testEveryProviderChoiceHasADriver(): void
    {
        $drivers = $this->read('assets/js/analytics.js');

        foreach (json_decode($this->read('config/configs.json'), true, 512, \JSON_THROW_ON_ERROR) as $entry) {
            if ('analytics-provider' === $entry['slug']) {
                foreach ($entry['choices'] as $slug) {
                    $this->assertMatchesRegularExpression(\sprintf('/%s: \(\) => import\("\.\/analytics-%s\.js"\)/', $slug, $slug), $drivers);
                }

                return;
            }
        }

        $this->fail('"analytics-provider" is no longer declared.');
    }

    // The keys renamed keep the values a site stored, and stay in the group holding the cookie banner
    public function testTheMatomoKeysAreRenamedWithTheirFormerSlugs(): void
    {
        $declared = [];
        foreach (json_decode($this->read('config/configs.json'), true, 512, \JSON_THROW_ON_ERROR) as $entry) {
            $declared[$entry['slug']] = $entry;
        }

        foreach (['analytics-matomo-url' => 'site-matomo-url', 'analytics-matomo-id' => 'site-matomo-id'] as $slug => $former) {
            $this->assertSame([$former], $declared[$slug]['former_slugs'] ?? null);
            $this->assertSame('analytics', $declared[$slug]['group']);
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
