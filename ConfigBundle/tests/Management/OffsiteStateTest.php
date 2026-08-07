<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\OffsiteState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class OffsiteStateTest extends TestCase
{
    private string $projectDir;
    private OffsiteState $state;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-offsite-state-test-' . uniqid();
        mkdir($this->projectDir, 0775, true);
        $this->state = new OffsiteState();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    // Nothing recorded is not "a while ago", it's "never", and the two deserve different words on the dashboard
    public function testNothingRecordedReadsAsNever(): void
    {
        $this->assertNull($this->state->read($this->projectDir));
        $this->assertNull($this->state->hoursSince($this->projectDir));
    }

    public function testASuccessfulTransferStartsTheClock(): void
    {
        $this->state->recordSuccess($this->projectDir, ['what' => 'mirror']);

        $this->assertLessThan(0.1, $this->state->hoursSince($this->projectDir));
        $this->assertSame('mirror', $this->state->read($this->projectDir)['what']);
    }

    // The one that matters: a failed run writing "at: now" would read as a fresh copy, and three nights of failures would look like three nights of success
    public function testAFailedTransferLeavesTheLastSuccessWhereItWas(): void
    {
        $this->state->recordSuccess($this->projectDir);
        $written = $this->state->read($this->projectDir);
        $written['at'] = (new \DateTimeImmutable('-40 hours'))->format(\DateTimeInterface::ATOM);
        file_put_contents($this->projectDir . '/' . OffsiteState::FILE, json_encode($written));

        $this->state->recordFailure($this->projectDir, 'connection refused');

        $state = $this->state->read($this->projectDir);
        $this->assertSame('failed', $state['status']);
        $this->assertSame('connection refused', $state['lastError']);
        $this->assertGreaterThan(39, $this->state->hoursSince($this->projectDir));
    }

    // Two streams write here and only one of them counts files: the archives push used to overwrite the whole file, so what the mirror had counted vanished every 6 hours
    public function testTheArchivesPushKeepsWhatTheMirrorCounted(): void
    {
        $this->state->recordSuccess($this->projectDir, ['what' => 'mirror', 'files' => 120, 'bytes' => 4096]);

        $this->state->recordSuccess($this->projectDir, ['what' => 'archives', 'target' => 'backup:site']);

        $state = $this->state->read($this->projectDir);
        $this->assertSame(120, $state['files']);
        $this->assertSame(4096, $state['bytes']);
        $this->assertSame('archives', $state['what']);
    }

    // A mirror broken every night for a month used to read "ok" because the archives push kept clearing a failure it knew nothing about
    public function testASuccessOnlyClearsTheFailureItsOwnStreamRaised(): void
    {
        $this->state->recordFailure($this->projectDir, 'connection refused', 'mirror');

        $this->state->recordSuccess($this->projectDir, ['what' => 'archives']);

        $state = $this->state->read($this->projectDir);
        $this->assertSame('failed', $state['status']);
        $this->assertSame('connection refused', $state['lastError']);

        $this->state->recordSuccess($this->projectDir, ['what' => 'mirror']);

        $state = $this->state->read($this->projectDir);
        $this->assertSame('ok', $state['status']);
        $this->assertNull($state['lastError']);
    }

    // A state file written before failures were attributed to a stream must not stay stuck on "failed" forever
    public function testAFailureNamingNoStreamClearsOnAnySuccess(): void
    {
        $this->state->recordFailure($this->projectDir, 'connection refused');

        $this->state->recordSuccess($this->projectDir, ['what' => 'archives']);

        $this->assertSame('ok', $this->state->read($this->projectDir)['status']);
    }

    // A file damaged by anything at all must not take the backup command down with it, the state being bookkeeping and not the backup itself
    public function testAnUnreadableStateFileReadsAsNever(): void
    {
        mkdir(\dirname($this->projectDir . '/' . OffsiteState::FILE), 0775, true);
        file_put_contents($this->projectDir . '/' . OffsiteState::FILE, 'not json at all');

        $this->assertNull($this->state->read($this->projectDir));
        $this->assertNull($this->state->hoursSince($this->projectDir));
    }
}
