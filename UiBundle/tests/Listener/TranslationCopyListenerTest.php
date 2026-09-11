<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Listener\TranslationCopyListener;
use c975L\UiBundle\Service\TranslationCopier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use PHPUnit\Framework\TestCase;

class TranslationCopyListenerTest extends TestCase
{
    private function createEvent(): PostFlushEventArgs
    {
        return new PostFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    // The copy only has an id once the flush that saved it is over, which is what makes this the moment its carried translations can be written
    public function testTheCarriedTranslationsAreWrittenOnTheFlushThatSavesTheCopy(): void
    {
        $copier = $this->createMock(TranslationCopier::class);
        $copier->expects($this->once())->method('write')->willReturn(true);

        new TranslationCopyListener($copier)->postFlush($this->createEvent());
    }

    // write() flushes, which brings Doctrine straight back here: without the guard, the second pass would write the same rows again
    public function testTheWriteDoesNotBringItselfBack(): void
    {
        $copier = $this->createMock(TranslationCopier::class);
        $listener = new TranslationCopyListener($copier);

        $copier->expects($this->once())
            ->method('write')
            ->willReturnCallback(function () use ($listener): bool {
                // What Doctrine does at the end of write()'s own flush
                $listener->postFlush($this->createEvent());

                return true;
            });

        $listener->postFlush($this->createEvent());
    }

    // A write that throws must not leave the guard up, or every later flush of a long-running worker would skip its copies without a word
    public function testAFailedWriteLeavesTheNextFlushWriting(): void
    {
        $calls = 0;
        $copier = $this->createMock(TranslationCopier::class);
        $copier->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(static function () use (&$calls): bool {
                if (1 === ++$calls) {
                    throw new \RuntimeException('The database went away.');
                }

                return true;
            });

        $listener = new TranslationCopyListener($copier);

        try {
            $listener->postFlush($this->createEvent());
        } catch (\RuntimeException) {
            // The first flush failing is the very situation under test
        }

        $listener->postFlush($this->createEvent());
    }
}
