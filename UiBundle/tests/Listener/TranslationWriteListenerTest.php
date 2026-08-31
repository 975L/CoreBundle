<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\TranslationWriteListener;
use c975L\UiBundle\Service\ContentTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use PHPUnit\Framework\TestCase;

class TranslationWriteListenerTest extends TestCase
{
    private function createEvent(): PostFlushEventArgs
    {
        return new PostFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    // The flush is the owner's own save, which a refused submission never reaches: that is what makes it the moment these become true
    public function testWhatAFormStagedIsWrittenOnTheFlushThatSavesItsOwner(): void
    {
        $translator = $this->createMock(ContentTranslator::class);
        $translator->method('takePending')->willReturn([[Translation::OWNER_BLOCK, 7, 'es', ['title' => 'Hola']]]);
        $translator->expects($this->once())->method('store')->with(Translation::OWNER_BLOCK, 7, 'es', ['title' => 'Hola']);

        new TranslationWriteListener($translator)->postFlush($this->createEvent());
    }

    // Every flush of the request comes through here, and almost none of them has anything waiting
    public function testAFlushWithNothingWaitingWritesNothing(): void
    {
        $translator = $this->createMock(ContentTranslator::class);
        $translator->method('takePending')->willReturn([]);
        $translator->expects($this->never())->method('store');

        new TranslationWriteListener($translator)->postFlush($this->createEvent());
    }

    // store() flushes, which brings Doctrine straight back here: without the guard, the second pass would write the same rows again
    public function testTheWriteDoesNotBringItselfBack(): void
    {
        $translator = $this->createMock(ContentTranslator::class);
        $listener = new TranslationWriteListener($translator);

        $translator->method('takePending')->willReturn([[Translation::OWNER_BLOCK, 7, 'es', ['title' => 'Hola']]]);
        $translator->expects($this->once())
            ->method('store')
            ->willReturnCallback(function () use ($listener): void {
                // What Doctrine does at the end of store()'s own flush
                $listener->postFlush($this->createEvent());
            });

        $listener->postFlush($this->createEvent());
    }
}
