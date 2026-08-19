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
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\ConfigBundle\Management\HealthCheckFrequencyAwareInterface;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Management\HealthCheckRunner;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class HealthCheckRunnerTest extends TestCase
{
    private function createProvider(string $kind, array $rows): HealthCheckProviderInterface
    {
        $provider = $this->createStub(HealthCheckProviderInterface::class);
        $provider->method('getKind')->willReturn($kind);
        $provider->method('runChecks')->willReturn($rows);

        return $provider;
    }

    private function createExhaustiveProvider(string $kind, array $rows): HealthCheckExhaustiveInterface
    {
        $provider = $this->createStub(HealthCheckExhaustiveInterface::class);
        $provider->method('getKind')->willReturn($kind);
        $provider->method('runChecks')->willReturn($rows);

        return $provider;
    }

    public function testRunPersistsOneHealthCheckResultPerRowAndFlushesOnce(): void
    {
        $rows = [
            ['url' => 'https://example.com/pages/home/', 'label' => 'Home', 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'Perf 95', 'details' => ['performance' => 95], 'editUrl' => '/admin/page/1/edit'],
            ['url' => 'https://example.com/pages/contact/', 'label' => null, 'status' => HealthCheckResult::STATUS_WARNING, 'summary' => 'Perf 60', 'details' => null],
        ];
        $provider = $this->createProvider('pagespeed', $rows);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (HealthCheckResult $result) use (&$persisted): void {
            $persisted[] = $result;
        });
        $entityManager->expects($this->once())->method('flush');

        $runner = new HealthCheckRunner([$provider], $entityManager, $this->createStub(HealthCheckResultRepository::class));
        $counts = $runner->run();

        $this->assertSame(['pagespeed' => 2], $counts);
        $this->assertCount(2, $persisted);

        $this->assertSame('pagespeed', $persisted[0]->getKind());
        $this->assertSame('https://example.com/pages/home/', $persisted[0]->getUrl());
        $this->assertSame('Home', $persisted[0]->getLabel());
        $this->assertSame(HealthCheckResult::STATUS_OK, $persisted[0]->getStatus());
        $this->assertSame('Perf 95', $persisted[0]->getSummary());
        $this->assertSame(['performance' => 95], $persisted[0]->getDetails());
        $this->assertSame('/admin/page/1/edit', $persisted[0]->getEditUrl());

        $this->assertNull($persisted[1]->getLabel());
        $this->assertNull($persisted[1]->getDetails());
        $this->assertNull($persisted[1]->getEditUrl());

        // Every row from the same provider run shares the same checkedAt, so they can be grouped as one run
        $this->assertEquals($persisted[0]->getCheckedAt(), $persisted[1]->getCheckedAt());
    }

    public function testRunReturnsZeroCountForAProviderWithNoRows(): void
    {
        $provider = $this->createProvider('w3c-html', []);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        // A run persisting nothing is a true no-op - flush() would only pay for computing a changeset with nothing in it
        $entityManager->expects($this->never())->method('flush');

        $runner = new HealthCheckRunner([$provider], $entityManager, $this->createStub(HealthCheckResultRepository::class));
        $counts = $runner->run();

        $this->assertSame(['w3c-html' => 0], $counts);
    }

    // Rows are only flushed once every provider has run, so a provider throwing would otherwise discard what the ones before it produced and stop the ones after it from running at all
    public function testAProviderThrowingDoesNotTakeTheRunDownWithIt(): void
    {
        $failing = $this->createStub(HealthCheckProviderInterface::class);
        $failing->method('getKind')->willReturn('intrusion');
        $failing->method('runChecks')->willThrowException(new \UnexpectedValueException('unreadable directory'));

        $working = $this->createProvider('pagespeed', [['url' => 'https://example.com/', 'label' => null, 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'ok', 'details' => null]]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $runner = new HealthCheckRunner([$failing, $working], $entityManager, $this->createStub(HealthCheckResultRepository::class));

        $this->assertSame(['pagespeed' => 1], $runner->run());
    }

    public function testRunWithNoProvidersReturnsEmptyCounts(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $runner = new HealthCheckRunner([], $entityManager, $this->createStub(HealthCheckResultRepository::class));

        $this->assertSame([], $runner->run());
    }

    // Lets the scheduler run a costly/paid provider (eg. "wave") on its own cron entry, separate from the free ones
    public function testRunWithOnlyKindsSkipsProvidersNotInTheList(): void
    {
        $pagespeed = $this->createProvider('pagespeed', [['url' => 'https://example.com/', 'label' => null, 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'ok', 'details' => null]]);
        $wave = $this->createProvider('wave', [['url' => 'https://example.com/', 'label' => null, 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'ok', 'details' => null]]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $runner = new HealthCheckRunner([$pagespeed, $wave], $entityManager, $this->createStub(HealthCheckResultRepository::class));
        $counts = $runner->run(['wave']);

        $this->assertSame(['wave' => 1], $counts);
    }

    public function testRunWithEmptyOnlyKindsRunsEveryProvider(): void
    {
        $pagespeed = $this->createProvider('pagespeed', []);
        $wave = $this->createProvider('wave', []);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $runner = new HealthCheckRunner([$pagespeed, $wave], $entityManager, $this->createStub(HealthCheckResultRepository::class));
        $counts = $runner->run();

        $this->assertSame(['pagespeed' => 0, 'wave' => 0], $counts);
    }

    // A url an exhaustive provider no longer returns can never go back to green on its own: results are kept per (url, kind) and the retention purge preserves the latest row of each pair, so a re-upload naming the file anew would leave the old ERROR standing for good
    public function testRunPurgesTheUrlsAnExhaustiveProviderNoLongerReturns(): void
    {
        $provider = $this->createExhaustiveProvider('files-ui', [
            ['url' => 'https://example.com/images/logo.png', 'label' => 'Logo', 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'found'],
        ]);

        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository
            ->expects($this->once())
            ->method('deleteByKindNotInUrls')
            ->with('files-ui', ['https://example.com/images/logo.png'])
            ->willReturn(3);

        $runner = new HealthCheckRunner([$provider], $this->createStub(EntityManagerInterface::class), $repository);

        $this->assertSame(['files-ui' => 1], $runner->run());
    }

    // Every other provider checks a fixed set of urls that never disappears, and its history is the point
    public function testRunNeverPurgesForAProviderThatIsNotExhaustive(): void
    {
        $provider = $this->createProvider('pagespeed', [
            ['url' => 'https://example.com/', 'label' => null, 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'ok'],
        ]);

        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->expects($this->never())->method('deleteByKindNotInUrls');

        $runner = new HealthCheckRunner([$provider], $this->createStub(EntityManagerInterface::class), $repository);
        $runner->run();
    }

    // An exhaustive run returning nothing is exactly the case where every row of that kind is stale - the last declared file was deleted, and its ERROR row must go with it
    public function testAnExhaustiveProviderReturningNoRowsClearsItsKind(): void
    {
        $provider = $this->createExhaustiveProvider('files-ui', []);

        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->expects($this->once())->method('deleteByKindNotInUrls')->with('files-ui', [])->willReturn(2);

        $runner = new HealthCheckRunner([$provider], $this->createStub(EntityManagerInterface::class), $repository);

        $this->assertSame(['files-ui' => 0], $runner->run());
    }

    // One class registered once per source shares its kind with its siblings (see DeclaredUrlsHealthCheckProvider): purging per instance would have each one delete what the ones before it just produced
    public function testTwoInstancesOfTheSameExhaustiveKindArePurgedOnceWithTheirUrlsMerged(): void
    {
        $first = $this->createExhaustiveProvider('files-ui', [
            ['url' => 'https://example.com/a.png', 'label' => null, 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'found'],
        ]);
        $second = $this->createExhaustiveProvider('files-ui', [
            ['url' => 'https://example.com/b.png', 'label' => null, 'status' => HealthCheckResult::STATUS_OK, 'summary' => 'found'],
        ]);

        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository
            ->expects($this->once())
            ->method('deleteByKindNotInUrls')
            ->with('files-ui', ['https://example.com/a.png', 'https://example.com/b.png'])
            ->willReturn(0);

        $runner = new HealthCheckRunner([$first, $second], $this->createStub(EntityManagerInterface::class), $repository);
        $runner->run();
    }

    // A provider throwing tells nothing about what it declares, so its rows must be left alone rather than taken for an empty domain
    public function testAnExhaustiveProviderThrowingDoesNotPurgeItsKind(): void
    {
        $provider = $this->createStub(HealthCheckExhaustiveInterface::class);
        $provider->method('getKind')->willReturn('files-ui');
        $provider->method('runChecks')->willThrowException(new \RuntimeException('unreadable directory'));

        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->expects($this->never())->method('deleteByKindNotInUrls');

        $runner = new HealthCheckRunner([$provider], $this->createStub(EntityManagerInterface::class), $repository);

        $this->assertSame([], $runner->run());
    }

    // What the dashboard's "Run health check now" button queues one job from (see HealthCheckController::run())
    public function testGetKindsListsEveryRegisteredKind(): void
    {
        $runner = new HealthCheckRunner(
            [$this->createProvider('pagespeed', []), $this->createProvider('wave', [])],
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(HealthCheckResultRepository::class),
        );

        $this->assertSame(['pagespeed', 'wave'], $runner->getKinds());
    }

    // Two providers of the same kind is one job to queue, not two identical ones
    public function testGetKindsDeduplicates(): void
    {
        $runner = new HealthCheckRunner(
            [$this->createProvider('urls-book', []), $this->createProvider('urls-book', [])],
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(HealthCheckResultRepository::class),
        );

        $this->assertSame(['urls-book'], $runner->getKinds());
    }

    public function testGetKindsIsEmptyWithoutAnyProvider(): void
    {
        $runner = new HealthCheckRunner([], $this->createStub(EntityManagerInterface::class), $this->createStub(HealthCheckResultRepository::class));

        $this->assertSame([], $runner->getKinds());
    }

    // A provider saying nothing is weekly, which is what keeps AsHealthCheck optional
    public function testAProviderWithoutTheAttributeIsWeekly(): void
    {
        $runner = $this->createFrequencyRunner();

        $this->assertSame(['pages' => 0], $runner->run([], AsHealthCheck::FREQUENCY_WEEKLY));
    }

    public function testOnlyTheProvidersDeclaringTheAskedCadenceRun(): void
    {
        $runner = $this->createFrequencyRunner();

        $this->assertSame(['photos' => 0], $runner->run([], AsHealthCheck::FREQUENCY_MONTHLY));
    }

    // What the dashboard's "Run health check now" button still does, and what a cron entry naming no cadence would
    public function testNoFrequencyRunsEveryProviderWhateverItDeclares(): void
    {
        $runner = $this->createFrequencyRunner();

        $this->assertSame(['pages' => 0, 'photos' => 0], $runner->run());
    }

    // Both filters narrow the same run rather than one winning over the other
    public function testKindAndFrequencyCombine(): void
    {
        $runner = $this->createFrequencyRunner();

        $this->assertSame([], $runner->run(['pages'], AsHealthCheck::FREQUENCY_MONTHLY));
        $this->assertSame(['pages' => 0], $runner->run(['pages'], AsHealthCheck::FREQUENCY_WEEKLY));
    }

    // One class registered once per source cannot state its cadence on itself, so the instance answers for it (see SiteBundle's DeclaredUrlsHealthCheckProvider)
    public function testAFrequencyAwareProviderDecidesPerInstance(): void
    {
        $runner = new HealthCheckRunner($this->createInstanceAwareProviders(), $this->createStub(EntityManagerInterface::class), $this->createStub(HealthCheckResultRepository::class));

        $this->assertSame(['urls-book' => 0], $runner->run([], AsHealthCheck::FREQUENCY_WEEKLY));
        $this->assertSame(['urls-gallery' => 0], $runner->run([], AsHealthCheck::FREQUENCY_MONTHLY));
    }

    // Two instances of the very same class, each with its own cadence - what the attribute alone cannot express
    private function createInstanceAwareProviders(): array
    {
        $provider = new readonly class ('urls-book', AsHealthCheck::FREQUENCY_WEEKLY) implements HealthCheckProviderInterface, HealthCheckFrequencyAwareInterface {
            public function __construct(private string $kind, private string $frequency)
            {
            }

            public function getKind(): string
            {
                return $this->kind;
            }

            public function getFrequency(): string
            {
                return $this->frequency;
            }

            public function runChecks(): array
            {
                return [];
            }
        };

        return [$provider, new ($provider::class)('urls-gallery', AsHealthCheck::FREQUENCY_MONTHLY)];
    }

    // One provider of each cadence: the weekly one says nothing, the monthly one carries the attribute
    private function createFrequencyRunner(): HealthCheckRunner
    {
        $weekly = new class implements HealthCheckProviderInterface {
            public function getKind(): string
            {
                return 'pages';
            }

            public function runChecks(): array
            {
                return [];
            }
        };

        $monthly = new #[AsHealthCheck(AsHealthCheck::FREQUENCY_MONTHLY)] class implements HealthCheckProviderInterface {
            public function getKind(): string
            {
                return 'photos';
            }

            public function runChecks(): array
            {
                return [];
            }
        };

        return new HealthCheckRunner([$weekly, $monthly], $this->createStub(EntityManagerInterface::class), $this->createStub(HealthCheckResultRepository::class));
    }
}
