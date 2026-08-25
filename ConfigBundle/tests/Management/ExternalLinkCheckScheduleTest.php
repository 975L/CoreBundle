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
use c975L\ConfigBundle\Management\ExternalLinkCheckSchedule;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class ExternalLinkCheckScheduleTest extends TestCase
{
    private const string NOW = '2026-08-25 12:00:00';

    private function createSchedule(array $rows): ExternalLinkCheckSchedule
    {
        $repository = $this->createStub(HealthCheckResultRepository::class);
        $repository->method('findLatestPerUrlAndKindIn')->willReturn($rows);

        return new ExternalLinkCheckSchedule($repository, new MockClock(self::NOW));
    }

    private function createResult(string $url, ?array $details, string $kind = 'urls-book'): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setKind($kind)
            ->setUrl($url)
            ->setStatus(HealthCheckResult::STATUS_OK)
            ->setSummary('ok')
            ->setDetails($details)
            ->setCheckedAt(new \DateTime(self::NOW));
    }

    // A url never checked before has nothing to report back, so its external links are called
    public function testDecideIsDueWhenNothingWasEverChecked(): void
    {
        $decision = $this->createSchedule([])->decide(['https://example.com/books/1']);

        $this->assertTrue($decision['due']);
        $this->assertSame([], $decision['broken']);
        $this->assertSame('2026-08-25T12:00:00+00:00', $decision['checkedAt']);
    }

    // Weekly runs in between two monthly passes leave the hosts alone and report back what the last real pass found
    public function testDecideReportsBackAPassYoungerThanTheInterval(): void
    {
        $schedule = $this->createSchedule([
            $this->createResult('https://example.com/books/1', [
                ExternalLinkCheckSchedule::CHECKED_AT_KEY => '2026-08-11T09:00:00+00:00',
                'brokenExternalLinks' => [['url' => 'https://shop.example/gone', 'text' => 'Acheter']],
            ]),
        ]);

        $decision = $schedule->decide(['https://example.com/books/1']);

        $this->assertFalse($decision['due']);
        $this->assertSame(['https://shop.example/gone' => true], $decision['broken']);
        // The previous date is carried over rather than stamped anew, otherwise the next run would find none and call the links again - monthly would quietly become weekly
        $this->assertSame('2026-08-11T09:00:00+00:00', $decision['checkedAt']);
    }

    public function testDecideIsDueAgainOnceTheIntervalHasPassed(): void
    {
        $schedule = $this->createSchedule([
            $this->createResult('https://example.com/books/1', [
                ExternalLinkCheckSchedule::CHECKED_AT_KEY => '2026-07-01T09:00:00+00:00',
                'brokenExternalLinks' => [['url' => 'https://shop.example/gone', 'text' => 'Acheter']],
            ]),
        ]);

        $decision = $schedule->decide(['https://example.com/books/1']);

        $this->assertTrue($decision['due']);
        // Nothing to carry back on a run calling the links itself - it produces its own verdicts
        $this->assertSame([], $decision['broken']);
        $this->assertSame('2026-08-25T12:00:00+00:00', $decision['checkedAt']);
    }

    // The same url is checked by several providers, and another kind's row - which never carries a date - must not hide the one that does
    public function testDecideIsNotFooledByAnotherKindCheckingTheSameUrl(): void
    {
        $schedule = $this->createSchedule([
            $this->createResult('https://example.com/books/1', ['brokenLinks' => []], 'sitemap-robots'),
            $this->createResult('https://example.com/books/1', [ExternalLinkCheckSchedule::CHECKED_AT_KEY => '2026-08-11T09:00:00+00:00'], 'urls-book'),
        ]);

        $decision = $schedule->decide(['https://example.com/books/1']);

        $this->assertFalse($decision['due']);
        $this->assertSame('2026-08-11T09:00:00+00:00', $decision['checkedAt']);
    }

    // Rows recorded before this check existed carry no date at all, and are simply not what decides
    public function testDecideIgnoresARowWithoutAnyDate(): void
    {
        $schedule = $this->createSchedule([
            $this->createResult('https://example.com/books/1', ['brokenExternalLinks' => []]),
            $this->createResult('https://example.com/books/2', null),
        ]);

        $this->assertTrue($schedule->decide(['https://example.com/books/1', 'https://example.com/books/2'])['due']);
    }

    // The most recent date of the batch decides for the whole batch, a run interrupted halfway having left the others behind
    public function testDecideFollowsTheMostRecentDateOfTheBatch(): void
    {
        $schedule = $this->createSchedule([
            $this->createResult('https://example.com/books/1', [ExternalLinkCheckSchedule::CHECKED_AT_KEY => '2026-06-01T09:00:00+00:00']),
            $this->createResult('https://example.com/books/2', [ExternalLinkCheckSchedule::CHECKED_AT_KEY => '2026-08-20T09:00:00+00:00']),
        ]);

        $decision = $schedule->decide(['https://example.com/books/1', 'https://example.com/books/2']);

        $this->assertFalse($decision['due']);
        $this->assertSame('2026-08-20T09:00:00+00:00', $decision['checkedAt']);
    }
}
