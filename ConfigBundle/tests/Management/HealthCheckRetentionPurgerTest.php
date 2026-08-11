<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\HealthCheckRetentionPurger;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class HealthCheckRetentionPurgerTest extends TestCase
{
    public function testPurgeDeletesPastTheConfiguredWindow(): void
    {
        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->method('findLatestIdPerUrlAndKind')->willReturn([12, 34]);
        $repository
            ->expects($this->once())
            ->method('deleteOlderThan')
            ->with(
                $this->callback(fn (\DateTimeInterface $limit) => 30 === new \DateTimeImmutable()->diff($limit)->days),
                [12, 34],
            )
            ->willReturn(417);

        $this->assertSame(417, new HealthCheckRetentionPurger($repository, $this->configService('30'))->purge());
    }

    // What the dashboard reads: a check whose latest run fell out of the window must keep that row, or the line saying how it went goes blank
    public function testPurgeKeepsTheLatestRowOfEachCheck(): void
    {
        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->expects($this->once())->method('findLatestIdPerUrlAndKind')->willReturn([7]);
        $repository->expects($this->once())->method('deleteOlderThan')->with($this->anything(), [7])->willReturn(0);

        new HealthCheckRetentionPurger($repository, $this->configService('90'))->purge();
    }

    // An entry anyone can mistype: read as "keep everything", the one reading that cannot destroy an install's whole health history. The empty string belongs with them rather than with the fallback below: the entry is declared "int", so a field cleared at the back-office reaches this as a 0 like a typed one
    public function testPurgeKeepsEverythingWhenTheRetentionIsZeroOrNegative(): void
    {
        foreach (['0', '-1', ''] as $days) {
            $repository = $this->createMock(HealthCheckResultRepository::class);
            $repository->expects($this->never())->method('deleteOlderThan');

            $this->assertSame(0, new HealthCheckRetentionPurger($repository, $this->configService($days))->purge());
        }
    }

    // An install that never got the config entry loaded still gets its history bounded
    public function testPurgeFallsBackToTheDefaultWindowWithoutAConfigEntry(): void
    {
        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->method('findLatestIdPerUrlAndKind')->willReturn([]);
        $repository
            ->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(
                fn (\DateTimeInterface $limit) => HealthCheckRetentionPurger::DEFAULT_RETENTION_DAYS === new \DateTimeImmutable()->diff($limit)->days
            ))
            ->willReturn(3);

        $this->assertSame(3, new HealthCheckRetentionPurger($repository, $this->configService(null))->purge());
    }

    private function configService(?string $days): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            fn (string $slug) => 'site-health-check-retention-days' === $slug ? $days : null
        );

        return $configService;
    }
}
