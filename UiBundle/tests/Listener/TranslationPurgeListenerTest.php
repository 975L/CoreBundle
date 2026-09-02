<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\TranslationPurgeListener;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class TranslationPurgeListenerTest extends TestCase
{
    private function createEvent(object $entity): PostRemoveEventArgs
    {
        return new PostRemoveEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    private function createBlock(?int $id): Block
    {
        $block = new Block();

        if (null !== $id) {
            new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);
        }

        return $block;
    }

    // No foreign key takes them along, so the removed block's translated title would stay in the table for good
    public function testABlockTakesItsTranslationsWithIt(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(Translation::OWNER_BLOCK, 7);

        new TranslationPurgeListener($repository)->postRemove($this->createEvent($this->createBlock(7)));
    }

    // postRemove fires for every entity of the flush, and this one only answers for blocks
    public function testAnythingButABlockIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new TranslationPurgeListener($repository)->postRemove($this->createEvent(new Media()));
    }

    // Taken out of its form's collection, a field is deleted by Doctrine's orphanRemoval - a removal like any other, and its translations have to go the same way
    public function testAFormFieldTakesItsTranslationsWithIt(): void
    {
        $field = new FormField();
        new \ReflectionProperty(FormField::class, 'id')->setValue($field, 12);

        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(Translation::OWNER_FORM_FIELD, 12);

        new TranslationPurgeListener($repository)->postRemove($this->createEvent($field));
    }

    // Named apart from the fields, so a result's own words are the ones taken away
    public function testAFormOutputTakesItsTranslationsWithIt(): void
    {
        $output = new FormOutput();
        new \ReflectionProperty(FormOutput::class, 'id')->setValue($output, 12);

        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(Translation::OWNER_FORM_OUTPUT, 12);

        new TranslationPurgeListener($repository)->postRemove($this->createEvent($output));
    }

    // A block that was never persisted owns no row keyed on an id it does not have
    public function testABlockWithNoIdDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new TranslationPurgeListener($repository)->postRemove($this->createEvent($this->createBlock(null)));
    }
}
