<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\TranslatableTextProviderInterface;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use Doctrine\ORM\EntityManagerInterface;

// The fields of the forms, which no page carries the text of: a "form" block holds the form, and the labels asking for a name and an email live on their own rows
class FormTextProvider implements TranslatableTextProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
    ) {
    }

    public function getTranslatableTexts(): iterable
    {
        $rows = [];

        foreach ($this->manager->getRepository(FormField::class)->findAll() as $field) {
            $this->collect($field, FormTranslator::OWNER_FIELD, FormTranslator::FIELD_FIELDS, $rows);
        }

        foreach ($this->manager->getRepository(FormOutput::class)->findAll() as $output) {
            $this->collect($output, FormTranslator::OWNER_OUTPUT, FormTranslator::OUTPUT_FIELDS, $rows);
        }

        return $rows;
    }

    // The translatable texts of one form field or one result line, read straight from the entity
    /**
     * @param list<string>                                                                           $fields
     * @param list<array{owner: string, ownerId: int, field: string, source: string, label: string}> $rows
     */
    private function collect(FormField | FormOutput $row, string $owner, array $fields, array &$rows): void
    {
        $id = $row->getId();
        if (null === $id) {
            return;
        }

        $label = 'Form ' . $row->getForm()?->getName();

        foreach ($fields as $field) {
            $source = $row->{'get' . ucfirst($field)}();

            if (\is_string($source) && '' !== trim($source)) {
                $rows[] = ['owner' => $owner, 'ownerId' => $id, 'field' => $field, 'source' => $source, 'label' => $label];
            }
        }
    }
}
