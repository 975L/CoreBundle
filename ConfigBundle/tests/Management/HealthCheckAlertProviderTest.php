<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAlertProvider;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HealthCheckAlertProviderTest extends TestCase
{
    // The aggregate the repository answers for one status
    private function counted(int $count, string $lastCheckedAt = '2026-07-27 04:00:00'): array
    {
        return ['count' => $count, 'lastCheckedAt' => new \DateTimeImmutable($lastCheckedAt)];
    }

    // Over a repository answering those counts per status, acknowledged rows already left out in SQL (see HealthCheckResultRepositoryTest)
    private function createProvider(array $counts): HealthCheckAlertProvider
    {
        $repository = $this->createStub(HealthCheckResultRepository::class);
        $repository->method('countLatestUnacknowledgedPerStatus')->willReturn($counts);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/management/health-check');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        // Names the very config entry the alert carries as its own role, the screen it opens being the admin's
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return new HealthCheckAlertProvider($repository, $configService, $urlGenerator, $translator);
    }

    public function testNoAlertWhenNothingHasEverBeenChecked(): void
    {
        $this->assertSame([], $this->createProvider([])->getAlerts());
    }

    // An alert saying "all good" would be permanent noise on a dashboard whose alerts are meant to be acted upon
    public function testNoAlertWhenEverythingIsOk(): void
    {
        $counts = [HealthCheckResult::STATUS_OK => $this->counted(3), HealthCheckResult::STATUS_SKIPPED => $this->counted(1)];

        $this->assertSame([], $this->createProvider($counts)->getAlerts());
    }

    public function testAnErrorRaisesADangerAlertCountingTheErrorsOnly(): void
    {
        $counts = [
            HealthCheckResult::STATUS_ERROR => $this->counted(2),
            HealthCheckResult::STATUS_WARNING => $this->counted(1),
            HealthCheckResult::STATUS_OK => $this->counted(1),
        ];

        $alerts = $this->createProvider($counts)->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.health_check_alert_errors', strtr($alerts[0]['label'], ['2' => '%count%']));
        $this->assertSame(Config::SEVERITY_DANGER, $alerts[0]['severity']);
        $this->assertSame('/management/health-check', $alerts[0]['url']);
    }

    public function testWarningsAloneRaiseAWarningAlert(): void
    {
        $counts = [HealthCheckResult::STATUS_WARNING => $this->counted(1), HealthCheckResult::STATUS_OK => $this->counted(1)];

        $alerts = $this->createProvider($counts)->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.health_check_alert_warnings', strtr($alerts[0]['label'], ['1' => '%count%']));
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
    }

    // The date is what makes the alert double as the "your queued run is done" signal
    public function testTheDescriptionCarriesTheMostRecentRunDate(): void
    {
        $counts = [
            HealthCheckResult::STATUS_ERROR => $this->counted(1, '2026-07-20 04:00:00'),
            HealthCheckResult::STATUS_OK => $this->counted(1, '2026-07-27 06:30:00'),
        ];

        $alerts = $this->createProvider($counts)->getAlerts();

        $this->assertSame('description.health_check_alert', strtr($alerts[0]['description'], ['27/07/2026 06:30' => '%date%']));
    }
}
