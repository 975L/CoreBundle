<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Listener\SeededTranslationWriteListener;
use c975L\UiBundle\Service\FormSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use PHPUnit\Framework\TestCase;

class SeededTranslationWriteListenerTest extends TestCase
{
    private function createEvent(): PostFlushEventArgs
    {
        return new PostFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    // The seeder never flushes - the caller decides when - so the flush that gave the fields their ids is the only moment their translations can be written
    public function testPostFlushWritesWhatTheSeederQueued(): void
    {
        $formSeeder = $this->createMock(FormSeeder::class);
        $formSeeder->expects($this->once())->method('writeQueuedTranslations');

        new SeededTranslationWriteListener($formSeeder)->postFlush($this->createEvent());
    }

    // Writing flushes, which brings the listener straight back: the second call must do nothing rather than recurse
    public function testPostFlushIgnoresTheFlushItsOwnWriteCauses(): void
    {
        $listener = null;
        $formSeeder = $this->createMock(FormSeeder::class);
        $formSeeder->expects($this->once())
            ->method('writeQueuedTranslations')
            ->willReturnCallback(function () use (&$listener): void {
                $listener->postFlush($this->createEvent());
            });

        $listener = new SeededTranslationWriteListener($formSeeder);
        $listener->postFlush($this->createEvent());
    }

    // The guard is lifted whatever the write did, so a run that threw does not leave every later flush ignored
    public function testAFailedWriteLeavesTheListenerArmed(): void
    {
        $formSeeder = $this->createMock(FormSeeder::class);
        $formSeeder->expects($this->exactly(2))
            ->method('writeQueuedTranslations')
            ->willReturnOnConsecutiveCalls($this->throwException(new \RuntimeException('flush failed')), null);

        $listener = new SeededTranslationWriteListener($formSeeder);

        try {
            $listener->postFlush($this->createEvent());
            $this->fail('The write was expected to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('flush failed', $exception->getMessage());
        }

        $listener->postFlush($this->createEvent());
    }
}
