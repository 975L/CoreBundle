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
use c975L\UiBundle\Management\FormExportProvider;
use c975L\UiBundle\Management\FormImportProvider;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

// The round trip a calculator makes between two environments: it is built and checked on one, and read by visitors on another, and its formulas are the one kind of content no deployment carries
class FormExportImportTest extends TestCase
{
    // A calculator is its fields, its formulas and the order of both - everything the other environment needs to compute the same numbers
    public function testTheExportCarriesTheFieldsTheFormulasAndTheirOrder(): void
    {
        $items = $this->export($this->createCalculator())['items'];

        $this->assertCount(1, $items);
        $this->assertSame('economies-e85', $items[0]['name']);
        $this->assertTrue($items[0]['outputsFirst']);
        $this->assertSame(['kilometres-par-an', 'type-de-vehicule'], array_column($items[0]['fields'], 'name'));
        $this->assertSame([5000.0, null], array_column($items[0]['fields'], 'minValue'));
        $this->assertSame([['label' => 'Léger', 'value' => '1.15']], $items[0]['fields'][1]['options']);
        $this->assertSame(['litres', 'economies'], array_column($items[0]['outputs'], 'name'));
        $this->assertSame('litres * 1.5', $items[0]['outputs'][1]['expression']);
        $this->assertSame([0, 1], array_column($items[0]['outputs'], 'position'));
    }

    // The variable name travels rather than being derived again on arrival: it is what the formulas read, and a slugger answering a shade differently on the other environment would break every one of them
    public function testTheVariableNamesTravelWithTheFormulasThatReadThem(): void
    {
        $items = $this->export($this->createCalculator())['items'];

        $this->assertSame('kilometres-par-an', $items[0]['fields'][0]['name']);
        $this->assertStringContainsString('litres', $items[0]['outputs'][1]['expression']);
    }

    // A Form the other environment does not have yet
    public function testImportingAnUnknownFormCreatesItWholeWithItsFormulas(): void
    {
        $created = new Form();
        $result = $this->import($this->export($this->createCalculator())['items'], null, $created);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertCount(2, $created->getFields());
        $this->assertCount(2, $created->getOutputs());
        $this->assertSame('litres * 1.5', $created->getOutputs()->get(1)?->getExpression());
        $this->assertTrue($created->isOutputsFirst());
    }

    // A field the export no longer carries goes, an output too: a formula reads its fields by name, so a Form left half-updated is one whose outputs no longer resolve
    public function testImportingOverAnExistingFormDropsWhatTheExportNoLongerCarries(): void
    {
        $existing = new Form()
            ->setName('economies-e85')
            ->addField(new FormField()->setName('vieux-champ')->setLabel('Vieux')->setType(FormField::TYPE_NUMBER))
            ->addOutput(new FormOutput()->setName('vieux-resultat')->setLabel('Vieux')->setExpression('1'));

        $result = $this->import($this->export($this->createCalculator())['items'], $existing);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame(['kilometres-par-an', 'type-de-vehicule'], $this->names($existing->getFields()->toArray()));
        $this->assertSame(['litres', 'economies'], $this->names($existing->getOutputs()->toArray()));
    }

    // The very case a synchronisation is made of: the Form is already there. An output re-created under a name the Form still holds would have Doctrine INSERT it before deleting the old row, and form_output_unique refuse the pair - so a homonym is updated in place instead
    public function testImportingOverAnExistingFormUpdatesAnOutputOfTheSameNameInPlace(): void
    {
        $kept = new FormOutput()->setName('litres')->setLabel('Vieux libellé')->setExpression('0')->setPosition(9);
        $existing = new Form()
            ->setName('economies-e85')
            ->addOutput($kept);

        $this->import($this->export($this->createCalculator())['items'], $existing);

        $this->assertSame($kept, $existing->getOutputs()->get(0), 'The output was rebuilt instead of being updated');
        $this->assertSame('Litres', $kept->getLabel());
        $this->assertSame('kilometres_par_an / 100', $kept->getExpression());
        $this->assertSame(0, $kept->getPosition());
        $this->assertSame(['litres', 'economies'], $this->names($existing->getOutputs()->toArray()));
    }

    // A restricted field is one its own bundle seeded and the application reads by name (a contact form's e-mail): an export that does not carry it must not take it out from under the site it lands on
    public function testImportingNeverRemovesAFieldTheApplicationItselfRequires(): void
    {
        $existing = new Form()
            ->setName('economies-e85')
            ->addField(new FormField()->setName('email')->setLabel('Votre e-mail')->setType(FormField::TYPE_EMAIL)->setRestricted(true));

        $this->import($this->export($this->createCalculator())['items'], $existing);

        $this->assertContains('email', $this->names($existing->getFields()->toArray()));
    }

    // A Form this import creates elsewhere has to be marked the way its own bundle would have marked it, or FormSeeder::backfillForm - which only ever brings a still-restricted row up to date - would never look at it again
    public function testTheRestrictedFlagIsAppliedOnTheRowThisImportCreates(): void
    {
        $seeded = new Form()
            ->setName('contact')
            ->setRestricted(true)
            ->addField(new FormField()->setName('email')->setLabel('Votre e-mail')->setType(FormField::TYPE_EMAIL)->setRestricted(true));

        $created = new Form();
        $this->import($this->export($seeded)['items'], null, $created);

        $this->assertTrue($created->isRestricted());
        $this->assertTrue($created->getFields()->get(0)?->isRestricted());
    }

    // A row that already exists keeps whatever its own environment marked it: a dev database must never be able to unmark what production's seeder maintains
    public function testTheRestrictedFlagOfAnExistingRowIsNeverOverwritten(): void
    {
        $existing = new Form()
            ->setName('economies-e85')
            ->setRestricted(true)
            ->addField(new FormField()->setName('kilometres-par-an')->setLabel('Vieux libellé')->setType(FormField::TYPE_RANGE)->setRestricted(true));

        // The export comes from a database where neither the Form nor the field is restricted
        $this->import($this->export($this->createCalculator())['items'], $existing);

        $this->assertTrue($existing->isRestricted());
        $field = $existing->getFields()->get(0);
        $this->assertTrue($field?->isRestricted());
        // Its editable side follows the export all the same: the label is what an admin changed on the other environment
        $this->assertSame('Kilomètres par an', $field?->getLabel());
    }

    // A Form owns no file, so the archive carries none - the shape ContentExporter still expects
    public function testTheExportDeclaresNoFile(): void
    {
        $this->assertSame([], $this->export($this->createCalculator())['files']);
    }

    private function createCalculator(): Form
    {
        return new Form()
            ->setName('economies-e85')
            ->setOutputsFirst(true)
            ->addField(new FormField()
                ->setName('kilometres-par-an')
                ->setLabel('Kilomètres par an')
                ->setType(FormField::TYPE_RANGE)
                ->setMinValue(5000)
                ->setMaxValue(40000)
                ->setStepValue(500)
                ->setDefaultValue('15000')
                ->setPosition(0))
            ->addField(new FormField()
                ->setName('type-de-vehicule')
                ->setLabel('Type de véhicule')
                ->setType(FormField::TYPE_CHOICE)
                ->setOptions([['label' => 'Léger', 'value' => '1.15']])
                ->setPosition(1))
            ->addOutput(new FormOutput()
                ->setName('litres')
                ->setLabel('Litres')
                ->setExpression('kilometres_par_an / 100')
                ->setPosition(0))
            ->addOutput(new FormOutput()
                ->setName('economies')
                ->setLabel('Économies')
                ->setExpression('litres * 1.5')
                ->setHighlighted(true)
                ->setPosition(1));
    }

    /** @return array{items: list<array<string, mixed>>, files: array<string, string>} */
    private function export(Form $form): array
    {
        return new FormExportProvider($this->createStub(FormRepository::class))->serialize([$form]);
    }

    /**
     * Runs the import against the Form the repository is made to answer with - $created catches the one a
     * create writes into, the entity manager being a stub that persists nowhere.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array{created: int, updated: int}
     */
    private function import(array $items, ?Form $existing = null, ?Form $created = null): array
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        if (null !== $created) {
            $em = $this->createMock(EntityManagerInterface::class);
            $em->expects($this->atLeastOnce())->method('persist')->willReturnCallback(static function (object $entity) use ($created): void {
                if ($entity instanceof Form) {
                    $created->setName((string) $entity->getName())->setOutputsFirst($entity->isOutputsFirst())->setRestricted($entity->isRestricted());
                    foreach ($entity->getFields() as $field) {
                        $created->addField($field);
                    }
                    foreach ($entity->getOutputs() as $output) {
                        $created->addOutput($output);
                    }
                }
            });
        }

        return new FormImportProvider($em, $repository)->import($items);
    }

    /**
     * @param list<FormField|FormOutput> $rows
     *
     * @return list<string>
     */
    private function names(array $rows): array
    {
        return array_values(array_map(static fn (FormField | FormOutput $row): string => (string) $row->getName(), $rows));
    }
}
