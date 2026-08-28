<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Repository\FormRepository;

// Serializes a Form with its fields and its outputs into the shape ContentExporter/FormImportProvider expect, so a calculator built and checked on one environment reaches another without being retyped - the one kind of content that used to have no way across, a formula being a string an admin wrote and not something a deployment carries. Carries no file: a Form owns none
class FormExportProvider implements ExportProviderInterface
{
    public function __construct(private readonly FormRepository $formRepository)
    {
    }

    public function getKind(): string
    {
        return FormImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->formRepository->findBy([], ['name' => 'ASC']));
    }

    /**
     * @param iterable<Form> $forms
     *
     * @return array{items: list<array<string, mixed>>, files: array<string, string>}
     */
    public function serialize(iterable $forms): array
    {
        $items = [];
        foreach ($forms as $form) {
            $items[] = [
                // The natural key: unique on the table, and the very name a "form" block stores to point at it
                'name' => $form->getName(),
                'action' => $form->getAction(),
                'actionConfig' => $form->getActionConfig(),
                'enabled' => $form->isEnabled(),
                // Carried so a row this export CREATES elsewhere is marked the way its own bundle would have marked it - never applied over a row that already exists, see FormImportProvider
                'restricted' => $form->isRestricted(),
                'outputsFirst' => $form->isOutputsFirst(),
                'fields' => array_map($this->exportField(...), $form->getFields()->toArray()),
                'outputs' => array_map($this->exportOutput(...), $form->getOutputs()->toArray()),
            ];
        }

        return ['items' => $items, 'files' => []];
    }

    /** @return array<string, mixed> */
    private function exportField(FormField $field): array
    {
        return [
            // Carried rather than re-derived on the way in: it is the variable the formulas read, and a slugger answering a shade differently on the other environment would break every one of them
            'name' => $field->getName(),
            'label' => $field->getLabel(),
            'type' => $field->getType(),
            'placeholder' => $field->getPlaceholder(),
            'url' => $field->getUrl(),
            'required' => $field->isRequired(),
            'restricted' => $field->isRestricted(),
            'position' => $field->getPosition(),
            'minValue' => $field->getMinValue(),
            'maxValue' => $field->getMaxValue(),
            'stepValue' => $field->getStepValue(),
            'defaultValue' => $field->getDefaultValue(),
            'options' => $field->getOptions(),
        ];
    }

    /** @return array<string, mixed> */
    private function exportOutput(FormOutput $output): array
    {
        return [
            'name' => $output->getName(),
            'label' => $output->getLabel(),
            'expression' => $output->getExpression(),
            'format' => $output->getFormat(),
            'decimals' => $output->getDecimals(),
            'unit' => $output->getUnit(),
            'visible' => $output->isVisible(),
            'highlighted' => $output->isHighlighted(),
            // An output only reads the outputs above it, so the order is the calculation itself and not a presentation detail
            'position' => $output->getPosition(),
        ];
    }
}
