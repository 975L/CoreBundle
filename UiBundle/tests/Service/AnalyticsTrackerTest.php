<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AnalyticsProviderInterface;
use c975L\UiBundle\Service\AnalyticsTracker;
use c975L\UiBundle\Twig\AnalyticsExtension;
use PHPUnit\Framework\TestCase;

// Which provider measures the site, whatever it is: the tracker snippet and every bundle pushing a measure ask this one place
class AnalyticsTrackerTest extends TestCase
{
    private function createTracker(?string $slug, bool $configured = true, string $providerSlug = 'acme'): AnalyticsTracker
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturn(null !== $slug);
        $configService->method('get')->willReturn($slug);

        $provider = $this->createStub(AnalyticsProviderInterface::class);
        $provider->method('getSlug')->willReturn($providerSlug);
        $provider->method('isConfigured')->willReturn($configured);

        return new AnalyticsTracker($configService, [$provider]);
    }

    public function testTheProviderNamedAndConfiguredIsInUse(): void
    {
        $tracker = $this->createTracker('acme');

        $this->assertSame('acme', $tracker->getProvider()?->getSlug());
        $this->assertTrue($tracker->isEnabled());
    }

    // An empty choice, an unknown slug or a provider missing its settings measure nothing
    public function testNothingIsMeasuredUnlessNamedRegisteredAndConfigured(): void
    {
        $this->assertFalse($this->createTracker(null)->isEnabled());
        $this->assertFalse($this->createTracker('')->isEnabled());
        $this->assertFalse($this->createTracker('other')->isEnabled());
        $this->assertFalse($this->createTracker('acme', configured: false)->isEnabled());
    }

    // Kept for the released PaymentBundle, which pushes into Matomo's own queue: true for Matomo only
    public function testMatomoEnabledAnswersForMatomoAlone(): void
    {
        $this->assertTrue(new AnalyticsExtension($this->createTracker('matomo', providerSlug: 'matomo'))->isMatomoEnabled());
        $this->assertFalse(new AnalyticsExtension($this->createTracker('acme'))->isMatomoEnabled());
        $this->assertTrue(new AnalyticsExtension($this->createTracker('acme'))->isEnabled());
    }
}
