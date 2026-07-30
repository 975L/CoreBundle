<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use c975L\UiBundle\Entity\AiUsage;
use c975L\UiBundle\Management\AiAlertProvider;
use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\AiUsageTracker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AiAlertProviderTest extends TestCase
{
    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([
            ['management_ui_ai_assistant_index', '/management/ui/ai-assistant'],
        ]);

        return $urlGenerator;
    }

    private function createUsageTracker(?AiUsage $currentMonth = null): AiUsageTracker
    {
        $tracker = $this->createStub(AiUsageTracker::class);
        $tracker->method('getCurrentMonth')->willReturn($currentMonth);

        return $tracker;
    }

    private function createAssistantClient(bool $enabled): AiAssistantClientInterface
    {
        $client = $this->createStub(AiAssistantClientInterface::class);
        $client->method('isEnabled')->willReturn($enabled);

        return $client;
    }

    private function createRephraseClient(bool $enabled): AiRephraseClient
    {
        $client = $this->createStub(AiRephraseClient::class);
        $client->method('isEnabled')->willReturn($enabled);

        return $client;
    }

    public function testReturnsBothNotEnabledAlertsOnAFreshInstall(): void
    {
        $provider = new AiAlertProvider(
            $this->createUsageTracker(),
            $this->createAssistantClient(false),
            $this->createRephraseClient(false),
            $this->createUrlGenerator(),
        );

        $labels = array_column($provider->getAlerts(), 'label');

        $this->assertSame(
            ['label.ai_assistant_dashboard_not_enabled', 'label.ai_rephrase_not_enabled'],
            $labels,
        );
    }

    public function testReturnsNoAlertWhenBothFeaturesAreFullyConfiguredAndNoFailure(): void
    {
        $provider = new AiAlertProvider(
            $this->createUsageTracker(),
            $this->createAssistantClient(true),
            $this->createRephraseClient(true),
            $this->createUrlGenerator(),
        );

        $this->assertSame([], $provider->getAlerts());
    }

    public function testReturnsOnlyTheRephraseNotEnabledAlertWhenDashboardIsConfigured(): void
    {
        $provider = new AiAlertProvider(
            $this->createUsageTracker(),
            $this->createAssistantClient(true),
            $this->createRephraseClient(false),
            $this->createUrlGenerator(),
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.ai_rephrase_not_enabled', $alerts[0]['label']);
        $this->assertSame(Config::SEVERITY_INFO, $alerts[0]['severity']);
    }

    public function testReturnsOnlyTheDashboardNotEnabledAlertWhenRephraseIsConfigured(): void
    {
        $provider = new AiAlertProvider(
            $this->createUsageTracker(),
            $this->createAssistantClient(false),
            $this->createRephraseClient(true),
            $this->createUrlGenerator(),
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.ai_assistant_dashboard_not_enabled', $alerts[0]['label']);
    }

    public function testReturnsAWarningAlertWhenLastRephraseAttemptFailed(): void
    {
        $usage = (new AiUsage())->setYearMonth('2026-07');
        $usage->recordFailure('HTTP 401 returned');

        $provider = new AiAlertProvider(
            $this->createUsageTracker($usage),
            $this->createAssistantClient(true),
            $this->createRephraseClient(true),
            $this->createUrlGenerator(),
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.ai_rephrase_failure_alert', $alerts[0]['label']);
        $this->assertSame('description.ai_rephrase_failure_alert', $alerts[0]['description']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
        $this->assertSame('/management/ui/ai-assistant', $alerts[0]['url']);
    }

    public function testReturnsNoFailureAlertWhenLastAttemptSucceeded(): void
    {
        $usage = (new AiUsage())->setYearMonth('2026-07');

        $provider = new AiAlertProvider(
            $this->createUsageTracker($usage),
            $this->createAssistantClient(true),
            $this->createRephraseClient(true),
            $this->createUrlGenerator(),
        );

        $this->assertSame([], $provider->getAlerts());
    }
}
