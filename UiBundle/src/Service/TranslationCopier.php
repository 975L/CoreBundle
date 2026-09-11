<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;

// What a copied row says in the site's other languages, carried over to its copy: a duplicated page, product or book is the same text in every language, and a copy losing its translations is one an editor translates a second time without being told. Two passes, a translation naming its owner by identifier (see Translation): a duplicator names each row and its copy as it builds the copy, and TranslationCopyListener writes them once the flush has given the copy its id
class TranslationCopier
{
    /** @var list<array{ownerType: string, source: object, copy: object, fields: list<string>|null}> */
    private array $pending = [];

    public function __construct(
        private readonly TranslationRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * A row and its copy, whose translations are written once the copy carries an id.
     *
     * @param list<string>|null $fields the fields carried over, every one of them when null - a copy given a title of its own on purpose keeps it in every language
     */
    public function copy(string $ownerType, object $source, object $copy, ?array $fields = null): void
    {
        $this->pending[] = ['ownerType' => $ownerType, 'source' => $source, 'copy' => $copy, 'fields' => $fields];
    }

    // Writes what copy() kept for every copy a flush has saved, and says whether anything was written - a copy not saved yet waits for the flush that saves it
    public function write(): bool
    {
        $waiting = [];
        $written = false;

        foreach ($this->pending as $entry) {
            $copyId = $this->identifier($entry['copy']);
            if (null === $copyId) {
                $waiting[] = $entry;

                continue;
            }

            $sourceId = $this->identifier($entry['source']);
            if (null === $sourceId) {
                continue;
            }

            foreach ($this->repository->findByOwner($entry['ownerType'], $sourceId) as $locale => $values) {
                foreach ($values as $field => $value) {
                    if (null === $value || (null !== $entry['fields'] && !\in_array($field, $entry['fields'], true))) {
                        continue;
                    }

                    $this->entityManager->persist(new Translation($entry['ownerType'], $copyId, $field, $locale)->setValue($value));
                    $written = true;
                }
            }
        }

        $this->pending = $waiting;

        if ($written) {
            $this->entityManager->flush();
        }

        return $written;
    }

    // Doctrine's own identifier, whatever the entity calls itself: every c975L entity carries getId()
    private function identifier(object $row): ?int
    {
        $id = method_exists($row, 'getId') ? $row->getId() : null;

        return \is_int($id) ? $id : null;
    }
}
