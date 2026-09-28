<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\FormTextProvider;
use c975L\UiBundle\Service\FormTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

// The labels of a form live on their own rows, and are read on whatever page carries it
class FormTextProviderTest extends TestCase
{
    public function testItCollectsTheTextsOfAFormField(): void
    {
        $field = new FormField();
        new \ReflectionProperty(FormField::class, 'id')->setValue($field, 3);
        $field->setLabel('Nom');
        $field->setPlaceholder(null);

        $fields = $this->createStub(EntityRepository::class);
        $fields->method('findAll')->willReturn([$field]);
        $outputs = $this->createStub(EntityRepository::class);
        $outputs->method('findAll')->willReturn([]);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnMap([[FormField::class, $fields], [FormOutput::class, $outputs]]);

        $rows = [...new FormTextProvider($manager)->getTranslatableTexts()];

        $this->assertCount(1, $rows);
        $this->assertSame(FormTranslator::OWNER_FIELD, $rows[0]['owner']);
        $this->assertSame('label', $rows[0]['field']);
        $this->assertSame('Nom', $rows[0]['source']);
    }
}
