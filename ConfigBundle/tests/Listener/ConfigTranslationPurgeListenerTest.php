<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Listener\ConfigTranslationPurgeListener;
use c975L\ConfigBundle\Service\ConfigTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class ConfigTranslationPurgeListenerTest extends TestCase
{
    // Nothing points at a translation, so nothing else takes it away: the next setting landing on this id would inherit the removed one's sentence
    public function testASettingTakesItsTranslationsWithIt(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(ConfigTranslator::OWNER, 42)
            ->willReturn(2);

        $this->remove($repository, $this->createConfig(42));
    }

    // A block is UiBundle's own listener's business, and would otherwise be purged twice under two owner types
    public function testAnotherEntityIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, new Block()->setKind('text'));
    }

    // A setting that was never persisted has no id to delete rows by, and every row would answer to "null"
    public function testASettingWithoutAnIdIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, $this->createConfig(null));
    }

    // postRemove alone has no id left to go on, whatever the row still says: that is the very state Doctrine hands it
    public function testPostRemoveWithoutPreRemoveDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new ConfigTranslationPurgeListener($repository)->postRemove(new PostRemoveEventArgs($this->createConfig(42), $this->createStub(EntityManagerInterface::class)));
    }

    // What Doctrine does with a removal: preRemove while the row still has its id, which it hands back to null before postRemove
    private function remove(TranslationRepository $repository, object $entity): void
    {
        $listener = new ConfigTranslationPurgeListener($repository);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener->preRemove(new PreRemoveEventArgs($entity, $entityManager));
        new \ReflectionProperty($entity, 'id')->setValue($entity, null);
        $listener->postRemove(new PostRemoveEventArgs($entity, $entityManager));
    }

    private function createConfig(?int $id): Config
    {
        $config = new Config();
        $config->setSlug('site-age-warning');
        $config->setLabel('label.site_age_warning');

        if (null !== $id) {
            new \ReflectionProperty(Config::class, 'id')->setValue($config, $id);
        }

        return $config;
    }
}
