<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Management\FormImportProvider;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * What an exported form becomes on the site that imports it.
 *
 * Everything is matched by name - the form, its fields, its outputs - so a relabelled or reordered row travels
 * without being rebuilt. The one thing an export can never take away is a restricted field: its own bundle seeded
 * it and the application reads it by name, so an archive that predates it must not pull it out from under the site.
 */
class FormImportProviderTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    // The plain case: nothing on the site yet, so the whole sheet is laid down
    public function testAFormTheSiteDoesNotHoldIsCreated(): void
    {
        $result = $this->import(null, [$this->sheet()]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertCount(1, $this->persisted);
    }

    // Matched by name: the form already there is written over rather than laid beside itself
    public function testAFormTheSiteAlreadyHoldsIsUpdated(): void
    {
        $result = $this->import(new Form()->setName('contact'), [$this->sheet()]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
    }

    // A sheet naming no form names nothing at all, and is skipped rather than creating an unnamed row
    public function testASheetCarryingNoNameIsSkipped(): void
    {
        $result = $this->import(null, [['action' => 'send_email']]);

        $this->assertSame(['created' => 0, 'updated' => 0], $result);
        $this->assertSame([], $this->persisted);
    }

    // The fields the sheet carries are written on the form, each under the name it is read by
    public function testTheFieldsOfTheSheetAreWritten(): void
    {
        $form = new Form()->setName('contact');
        $this->import($form, [$this->sheet(fields: [
            ['name' => 'email', 'label' => 'Courriel', 'type' => 'email', 'required' => true],
        ])]);

        $this->assertCount(1, $form->getFields());
        $this->assertSame('Courriel', $form->getFields()->first()->getLabel());
    }

    // A field short of one of its three required keys describes nothing renderable, and is skipped
    public function testAFieldMissingItsTypeIsSkipped(): void
    {
        $form = new Form()->setName('contact');
        $this->import($form, [$this->sheet(fields: [['name' => 'email', 'label' => 'Courriel']])]);

        $this->assertCount(0, $form->getFields());
    }

    // Matched by name too: the row keeps its identity, so a relabelling is not a delete followed by an insert
    public function testAFieldAlreadyThereIsRelabelledInPlace(): void
    {
        $existing = new FormField()->setName('email')->setLabel('E-mail')->setType('email');
        $form = new Form()->setName('contact')->addField($existing);

        $this->import($form, [$this->sheet(fields: [
            ['name' => 'email', 'label' => 'Courriel', 'type' => 'email'],
        ])]);

        $this->assertCount(1, $form->getFields());
        $this->assertSame('Courriel', $existing->getLabel());
    }

    // What the export no longer carries is taken off the form
    public function testAFieldTheSheetNoLongerCarriesIsDropped(): void
    {
        $form = new Form()->setName('contact')->addField(new FormField()->setName('phone')->setLabel('Téléphone')->setType('text'));

        $this->import($form, [$this->sheet(fields: [])]);

        $this->assertCount(0, $form->getFields());
    }

    // Except a restricted one: its bundle seeded it and the application reads it by name, so an archive predating it must not pull it out from under the site
    public function testARestrictedFieldSurvivesASheetThatDroppedIt(): void
    {
        $restricted = new FormField()->setName('email')->setLabel('Courriel')->setType('email');
        $restricted->setRestricted(true);
        $form = new Form()->setName('contact')->addField($restricted);

        $this->import($form, [$this->sheet(fields: [])]);

        $this->assertCount(1, $form->getFields());
    }

    // "restricted" is applied on creation alone: a dev database must never unmark what production's seeder maintains
    public function testAnExistingFieldKeepsItsOwnRestrictedFlag(): void
    {
        $restricted = new FormField()->setName('email')->setLabel('Courriel')->setType('email');
        $restricted->setRestricted(true);
        $form = new Form()->setName('contact')->addField($restricted);

        $this->import($form, [$this->sheet(fields: [
            ['name' => 'email', 'label' => 'Courriel', 'type' => 'email', 'restricted' => false],
        ])]);

        $this->assertTrue($restricted->isRestricted());
    }

    // A field the import creates is marked the way its own bundle would have marked it
    public function testAFieldCreatedByTheImportTakesItsRestrictedFlag(): void
    {
        $form = new Form()->setName('contact');
        $this->import($form, [$this->sheet(fields: [
            ['name' => 'email', 'label' => 'Courriel', 'type' => 'email', 'restricted' => true],
        ])]);

        $this->assertTrue($form->getFields()->first()->isRestricted());
    }

    // The outputs travel on the same rule, matched by the name every expression reads them by
    public function testTheOutputsOfTheSheetAreWritten(): void
    {
        $form = new Form()->setName('calculator');
        $this->import($form, [$this->sheet(outputs: [
            ['name' => 'total', 'label' => 'Total', 'expression' => 'a + b', 'decimals' => 2, 'unit' => '€'],
        ])]);

        $this->assertCount(1, $form->getOutputs());
        $this->assertSame('a + b', $form->getOutputs()->first()->getExpression());
        $this->assertSame(2, $form->getOutputs()->first()->getDecimals());
    }

    // An output has no restricted flag, nothing but the admin ever writing one: what the sheet drops is dropped
    public function testAnOutputTheSheetNoLongerCarriesIsDropped(): void
    {
        $form = new Form()->setName('calculator')->addOutput(new FormOutput()->setName('total')->setLabel('Total')->setExpression('a + b'));

        $this->import($form, [$this->sheet(outputs: [])]);

        $this->assertCount(0, $form->getOutputs());
    }

    // An output short of its expression computes nothing, and is skipped
    public function testAnOutputMissingItsExpressionIsSkipped(): void
    {
        $form = new Form()->setName('calculator');
        $this->import($form, [$this->sheet(outputs: [['name' => 'total', 'label' => 'Total']])]);

        $this->assertCount(0, $form->getOutputs());
    }

    // The form's own settings follow the sheet, "enabled" defaulting to on for an archive that predates the column
    public function testTheFormSettingsFollowTheSheet(): void
    {
        $form = new Form()->setName('contact');
        $this->import($form, [$this->sheet() + ['action' => 'send_email', 'outputsFirst' => true]]);

        $this->assertSame('send_email', $form->getAction());
        $this->assertTrue($form->isEnabled());
        $this->assertTrue($form->isOutputsFirst());
    }

    private function sheet(array $fields = [], array $outputs = []): array
    {
        return ['name' => 'contact', 'fields' => $fields, 'outputs' => $outputs];
    }

    private function import(?Form $existing, array $items): array
    {
        $this->persisted = [];

        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new FormImportProvider($em, $repository)->import($items);
    }
}
