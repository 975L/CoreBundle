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
use c975L\UiBundle\Repository\SharedBlockRepository;

// The blocks of the shared blocks, which no page carries the text of: a page's pointer holds a slug, the words living on the shared block's own run
class SharedBlockTextProvider implements TranslatableTextProviderInterface
{
    public function __construct(
        private readonly SharedBlockRepository $sharedBlockRepository,
        private readonly BlockTextCollector $blockTextCollector,
    ) {
    }

    // One label per shared block, by name, so a run of the command says which one it is writing
    public function getTranslatableTexts(): iterable
    {
        $rows = [];

        foreach ($this->sharedBlockRepository->findBy([], ['name' => 'ASC']) as $sharedBlock) {
            array_push($rows, ...$this->blockTextCollector->collect($sharedBlock->getBlocks(), 'Bloc partagé ' . $sharedBlock->getName()));
        }

        return $rows;
    }
}
