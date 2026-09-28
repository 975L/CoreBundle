<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;

// The translatable texts of a set of blocks, slots and medias included, for whichever bundle owns them - a page, a menu - to hand them to "c975l:translate:content" (see TranslatableTextProviderInterface)
class BlockTextCollector
{
    public function __construct(
        private readonly BlockRegistry $blockRegistry,
    ) {
    }

    // Every text of those blocks holding words, labelled after their owner
    /**
     * @param iterable<Block> $blocks
     *
     * @return list<array{owner: string, ownerId: int, field: string, source: string, label: string}>
     */
    public function collect(iterable $blocks, string $label): array
    {
        $rows = [];
        $seen = [];
        $this->walk($blocks, $label, $rows, $seen);

        return $rows;
    }

    /**
     * @param iterable<Block>                                                                        $blocks
     * @param list<array{owner: string, ownerId: int, field: string, source: string, label: string}> $rows
     * @param array<int, bool>                                                                       $seen
     */
    private function walk(iterable $blocks, string $label, array &$rows, array &$seen): void
    {
        foreach ($blocks as $block) {
            $id = $block->getId();
            $kind = $block->getKind();

            // The guard every walk of the blocks carries: a container and one of its slots pointing at each other
            if (null === $id || null === $kind || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $data = $block->getData();
            $blockLabel = $label . ' / ' . $this->blockRegistry->getLabel($kind);

            // The repeated texts too - a FAQ's questions, a grid's cards - named one entry at a time off the data itself (see ContentTranslator::expand)
            $fields = ContentTranslator::expand(
                $data,
                $this->blockRegistry->getTranslatable($kind),
                $this->blockRegistry->getTranslatableCollections($kind),
            );

            foreach ($fields as $field) {
                $source = $this->read($data, $field);

                if (\is_string($source) && '' !== trim($source)) {
                    $rows[] = ['owner' => Translation::OWNER_BLOCK, 'ownerId' => $id, 'field' => $field, 'source' => $source, 'label' => $blockLabel];
                }
            }

            // A grid's cards carry their title and their text on the media rather than in the block's data, and the pass would otherwise leave every card of a translated page in the writing language (see MediaTranslator)
            foreach ($block->getMedias() as $media) {
                $mediaId = $media->getId();
                if (null === $mediaId) {
                    continue;
                }

                foreach (MediaTranslator::FIELDS as $field) {
                    $source = $media->getUntranslated($field);

                    if (\is_string($source) && '' !== trim($source)) {
                        $rows[] = ['owner' => Translation::OWNER_MEDIA, 'ownerId' => $mediaId, 'field' => $field, 'source' => $source, 'label' => $blockLabel];
                    }
                }
            }

            $this->walk($block->getSlots(), $label, $rows, $seen);
        }
    }

    // One value of a block's data, "cards.0.title" reaching into the collection it holds as json
    /** @param array<string, mixed> $data */
    private function read(array $data, string $field): mixed
    {
        $value = $data;

        foreach (explode('.', $field) as $step) {
            if (!\is_array($value) || !\array_key_exists($step, $value)) {
                return null;
            }

            $value = $value[$step];
        }

        return $value;
    }
}
