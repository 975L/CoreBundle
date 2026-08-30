<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "site_form" content export (see FormExportProvider/ContentExporter) - matched by name, the column the table already keeps unique and the one a "form" block stores to point at its Form, so dev and prod ids never have to line up.
// The fields and the outputs are both matched by name, the column each of the two tables keeps unique per Form and the one every expression reads them by, so a relabelled or reordered row travels without being rebuilt - and so an import replayed on a database that already holds the Form does not delete and re-insert the same name in one flush, which Doctrine orders INSERT first and the unique index refuses. A field the payload does not carry is removed unless it is restricted - a field its own bundle seeded and the application reads by name (a contact form's e-mail) must not be taken out from under the site by an export that predates it - while an output has no such flag, nothing but the admin ever writing one.
// "restricted" itself, at both levels, is applied only when this import CREATES the row: a row that already exists keeps whatever its own environment marked it, so a dev database can never unmark what production's seeder maintains (FormSeeder::backfillForm only ever brings a still-restricted row up to date, so a Form unmarked here would be orphaned from it for good). Carrying it at all is what lets a Form created by this import be marked the way its own bundle would have marked it
class FormImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_form';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FormRepository $formRepository,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            if (!isset($item['name'])) {
                continue;
            }

            $form = $this->formRepository->findOneBy(['name' => $item['name']]);
            $isNew = null === $form;
            $form ??= new Form()->setName($item['name']);

            $this->fillForm($form, $item, $isNew);
            $this->mergeFields($form, $item['fields'] ?? []);
            $this->mergeOutputs($form, $item['outputs'] ?? []);

            $this->em->persist($form);
            $isNew ? ++$created : ++$updated;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // The form's own settings - "restricted" only on the way in, an environment having its own say on whether the form may be edited there
    private function fillForm(Form $form, array $item, bool $isNew): void
    {
        $form
            ->setAction($item['action'] ?? null)
            ->setActionConfig($item['actionConfig'] ?? null)
            ->setEnabled((bool) ($item['enabled'] ?? true))
            ->setOutputsFirst((bool) ($item['outputsFirst'] ?? false));

        if ($isNew) {
            $form->setRestricted((bool) ($item['restricted'] ?? false));
        }
    }

    /** @param list<array<string, mixed>> $fields */
    private function mergeFields(Form $form, array $fields): void
    {
        $existing = [];
        foreach ($form->getFields()->toArray() as $field) {
            $existing[(string) $field->getName()] = $field;
        }

        $carried = [];
        foreach ($fields as $data) {
            if (!isset($data['name'], $data['label'], $data['type'])) {
                continue;
            }

            $name = (string) $data['name'];
            $carried[] = $name;
            $this->writeField($form, $existing[$name] ?? null, $name, $data);
        }

        // What the export does not carry any more, dropped - except a restricted field, which the application itself reads by name
        foreach ($existing as $name => $field) {
            if (!$field->isRestricted() && !in_array($name, $carried, true)) {
                $form->removeField($field);
            }
        }
    }

    /** @param list<array<string, mixed>> $outputs */
    private function mergeOutputs(Form $form, array $outputs): void
    {
        $existing = [];
        foreach ($form->getOutputs()->toArray() as $output) {
            $existing[(string) $output->getName()] = $output;
        }

        $carried = [];
        foreach ($outputs as $data) {
            if (!isset($data['name'], $data['label'], $data['expression'])) {
                continue;
            }

            $name = (string) $data['name'];
            $carried[] = $name;
            $this->writeOutput($form, $existing[$name] ?? null, $name, $data);
        }

        // What the export does not carry any more, dropped - an output being written by the admin alone, nothing seeds one to protect
        foreach ($existing as $name => $output) {
            if (!in_array($name, $carried, true)) {
                $form->removeOutput($output);
            }
        }
    }

    // Updated in place when it is already there, so a relabelled or reordered field travels without the row it belongs to being rebuilt - and so a restricted field keeps the flag its own environment set on it while its editable side follows the export
    private function writeField(Form $form, ?FormField $existing, string $name, array $data): void
    {
        $field = $existing ?? new FormField()->setName($name);

        $field
            ->setLabel($data['label'])
            ->setType($data['type'])
            ->setPlaceholder($data['placeholder'] ?? null)
            ->setUrl($data['url'] ?? null);

        $this->writeFieldConstraints($field, $data);
        $this->writeFieldRange($field, $data);

        if (null === $existing) {
            $field->setRestricted((bool) ($data['restricted'] ?? false));
            $form->addField($field);
        }
    }

    // Whether the visitor may leave it blank, and where it stands among the others
    private function writeFieldConstraints(FormField $field, array $data): void
    {
        $field
            ->setRequired((bool) ($data['required'] ?? false))
            ->setPosition($data['position'] ?? null);
    }

    // What a numeric or a choice field is bounded by, all four optional: a plain text field carries none of them
    private function writeFieldRange(FormField $field, array $data): void
    {
        $field
            ->setMinValue($data['minValue'] ?? null)
            ->setMaxValue($data['maxValue'] ?? null)
            ->setStepValue($data['stepValue'] ?? null)
            ->setDefaultValue($data['defaultValue'] ?? null)
            ->setOptions($data['options'] ?? null);
    }

    // Updated in place when it is already there, so the row a formula names keeps its identity instead of being dropped and re-inserted under the same name in the same flush
    private function writeOutput(Form $form, ?FormOutput $existing, string $name, array $data): void
    {
        $output = $existing ?? new FormOutput()->setName($name);

        $output
            ->setLabel($data['label'])
            ->setExpression($data['expression'])
            ->setPosition($data['position'] ?? null);

        $this->writeOutputPresentation($output, $data);

        if (null === $existing) {
            $form->addOutput($output);
        }
    }

    // How the computed value is read out: the shape of the number, and whether it is shown at all
    private function writeOutputPresentation(FormOutput $output, array $data): void
    {
        $output
            ->setFormat($data['format'] ?? FormOutput::FORMAT_NUMBER)
            ->setDecimals((int) ($data['decimals'] ?? 0))
            ->setUnit($data['unit'] ?? null)
            ->setVisible((bool) ($data['visible'] ?? true))
            ->setHighlighted((bool) ($data['highlighted'] ?? false));
    }
}
