<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity\Trait;

use c975L\UiBundle\Contract\TrashableInterface;
use c975L\UiBundle\Entity\Trait\TrashableTrait;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;

class TrashableTraitTest extends TestCase
{
    // An entity is on the site until it is deleted, and a row added by a migration answers the same as a brand new object
    public function testAnEntityIsNotDeletedToStartWith(): void
    {
        $this->assertFalse(new TrashableTraitStub()->isDeleted());
    }

    public function testTheFlagIsWrittenAndReadBack(): void
    {
        $entity = new TrashableTraitStub()->setIsDeleted(true);

        $this->assertTrue($entity->isDeleted());
    }

    // What restores an entity is writing the flag back, nothing having been kept aside to restore it from
    public function testWritingTheFlagBackRestoresTheEntity(): void
    {
        $entity = new TrashableTraitStub()->setIsDeleted(true)->setIsDeleted(false);

        $this->assertFalse($entity->isDeleted());
    }

    public function testTheSetterReturnsTheEntityItself(): void
    {
        $entity = new TrashableTraitStub();

        $this->assertSame($entity, $entity->setIsDeleted(true));
    }

    public function testAnEntityUsingTheTraitAnswersTheContract(): void
    {
        $this->assertInstanceOf(TrashableInterface::class, new TrashableTraitStub());
    }

    // Unlike HasBlocksTrait leaving $blocks to the entity, the mapping is the trait's own: a relation's differs at every use, a boolean column's never does
    public function testTheTraitCarriesTheColumnMappingItself(): void
    {
        $attributes = new \ReflectionProperty(TrashableTrait::class, 'isDeleted')->getAttributes(ORM\Column::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(['default' => false], $attributes[0]->newInstance()->options);
    }
}
