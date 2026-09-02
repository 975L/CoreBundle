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
use c975L\ConfigBundle\Management\HealthCheckReportBuilder;
use c975L\ConfigBundle\Management\StatusReportBuilder;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class HealthCheckReportBuilderTest extends TestCase
{
    // One health check row, only the fields the report reads being set
    private function row(string $status, ?\DateTimeInterface $acknowledgedAt = null): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setKind('w3c-css')
            ->setUrl('https://example.com/shop')
            ->setLabel('Boutique')
            ->setStatus($status)
            ->setSummary('2 errors')
            ->setDetails(['errors' => ['line 3: The types are incompatible']])
            ->setCheckedAt(new \DateTimeImmutable('2026-08-25 03:00:00'))
            ->setAcknowledgedAt($acknowledgedAt)
        ;
    }

    // Both builders read the same stubbed repository: the report is the status one plus the details it leaves out, so the two have to be looking at the very same run
    private function createBuilder(array $rows = []): HealthCheckReportBuilder
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $repository = $this->createStub(HealthCheckResultRepository::class);
        $repository->method('findLatestPerUrlAndKind')->willReturn($rows);

        return new HealthCheckReportBuilder(
            new StatusReportBuilder([], $configService, $repository, 'prod'),
            $repository,
        );
    }

    // Whoever reads the report gets the site's identity and versions for free: a bug read without the versions it happens on is a bug read twice
    public function testTheReportCarriesTheWholeStatusReport(): void
    {
        $report = $this->createBuilder()->build();

        $this->assertSame(HealthCheckReportBuilder::VERSION, $report['reportVersion']);
        $this->assertSame(StatusReportBuilder::VERSION, $report['version']);
        $this->assertSame('https://example.com', $report['site']);
        $this->assertArrayHasKey('packages', $report);
        $this->assertArrayHasKey('checks', $report);
    }

    // The whole reason this report exists rather than /status/report being read: the checkers' own payloads, which is what makes a row actionable
    public function testEveryReportedRowCarriesItsDetails(): void
    {
        $results = $this->createBuilder([$this->row(HealthCheckResult::STATUS_ERROR)])->build()['results'];

        $this->assertCount(1, $results);
        $this->assertSame('w3c-css', $results[0]['kind']);
        $this->assertSame('Boutique', $results[0]['label']);
        $this->assertSame(['errors' => ['line 3: The types are incompatible']], $results[0]['details']);
        $this->assertNull($results[0]['acknowledgedAt']);
    }

    // An "ok" row says nothing to fix and a "skipped" one says the check never ran - both would bury the rows that need reading under a site's whole page count
    public function testOnlyTheRowsNeedingActionAreListed(): void
    {
        $report = $this->createBuilder([
            $this->row(HealthCheckResult::STATUS_OK),
            $this->row(HealthCheckResult::STATUS_SKIPPED),
            $this->row(HealthCheckResult::STATUS_WARNING),
            $this->row(HealthCheckResult::STATUS_ERROR),
        ])->build();

        $this->assertSame(
            [HealthCheckResult::STATUS_WARNING, HealthCheckResult::STATUS_ERROR],
            array_column($report['results'], 'status')
        );

        // The counts still say what was checked, so the list above never reads as the whole run
        $this->assertSame(1, $report['checks']['counts'][HealthCheckResult::STATUS_OK]);
        $this->assertSame(1, $report['checks']['counts'][HealthCheckResult::STATUS_SKIPPED]);
    }

    // A row an admin declared dealt with leaves the dashboard's default view: leaving it out here too would have whoever reads the report wonder why a known problem is not in it
    public function testAnAcknowledgedRowIsCarriedWithItsDate(): void
    {
        $results = $this->createBuilder([
            $this->row(HealthCheckResult::STATUS_ERROR, new \DateTimeImmutable('2026-08-25 09:30:00')),
        ])->build()['results'];

        $this->assertCount(1, $results);
        $this->assertStringStartsWith('2026-08-25T09:30:00', (string) $results[0]['acknowledgedAt']);
    }
}
