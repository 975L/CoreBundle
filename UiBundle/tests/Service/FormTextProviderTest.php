<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Form;
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

        $rows = [...new FormTextProvider($this->manager([$field], []))->getTranslatableTexts()];

        $this->assertCount(1, $rows);
        $this->assertSame(FormTranslator::OWNER_FIELD, $rows[0]['owner']);
        $this->assertSame('label', $rows[0]['field']);
        $this->assertSame('Nom', $rows[0]['source']);
    }

    // The links under a form's button are handed over under the form itself, one label apiece
    public function testItCollectsTheLabelsOfAFormsLinks(): void
    {
        $form = new Form()->setName('register')->setLinks([['label' => 'Se connecter', 'url' => '/login']]);
        new \ReflectionProperty(Form::class, 'id')->setValue($form, 4);

        $rows = [...new FormTextProvider($this->manager([], [$form]))->getTranslatableTexts()];

        $this->assertSame([['owner' => FormTranslator::OWNER_FORM, 'ownerId' => 4, 'field' => 'links.0.label', 'source' => 'Se connecter', 'label' => 'Form register']], $rows);
    }

    /**
     * @param list<FormField> $fields
     * @param list<Form>      $forms
     */
    private function manager(array $fields, array $forms): EntityManagerInterface
    {
        $repositories = [];
        foreach ([FormField::class => $fields, FormOutput::class => [], Form::class => $forms] as $class => $rows) {
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('findAll')->willReturn($rows);
            $repositories[] = [$class, $repository];
        }

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnMap($repositories);

        return $manager;
    }
}
