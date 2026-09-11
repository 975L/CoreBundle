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
use c975L\UiBundle\Entity\Favorite;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\TranslationPurgeListener;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class TranslationPurgeListenerTest extends TestCase
{
    // What Doctrine does with a removal: preRemove while the row still has its id, which it hands back to null before postRemove
    private function remove(TranslationRepository $repository, object $entity): void
    {
        $listener = new TranslationPurgeListener($repository);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener->preRemove(new PreRemoveEventArgs($entity, $entityManager));
        if (property_exists($entity, 'id')) {
            new \ReflectionProperty($entity, 'id')->setValue($entity, null);
        }
        $listener->postRemove(new PostRemoveEventArgs($entity, $entityManager));
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

        $this->remove($repository, $this->createBlock(7));
    }

    // postRemove fires for every entity of the flush, and this one only answers for the four that carry translations
    public function testAnythingCarryingNoTranslationIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, new Favorite());
    }

    // A card taken off its grid is orphan-removed the same way a form field is, and the title and text it was given in each language have to go with it (see MediaTranslator)
    public function testAMediaTakesItsTranslationsWithIt(): void
    {
        $media = new Media();
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 21);

        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(Translation::OWNER_MEDIA, 21);

        $this->remove($repository, $media);
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

        $this->remove($repository, $field);
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

        $this->remove($repository, $output);
    }

    // A block that was never persisted owns no row keyed on an id it does not have
    public function testABlockWithNoIdDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, $this->createBlock(null));
    }

    // postRemove alone has no id left to go on, whatever the row still says: that is the very state Doctrine hands it
    public function testPostRemoveWithoutPreRemoveDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new TranslationPurgeListener($repository)->postRemove(new PostRemoveEventArgs($this->createBlock(7), $this->createStub(EntityManagerInterface::class)));
    }
}
