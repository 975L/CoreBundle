<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Service\ContentTranslator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

// Writes what a form left waiting (see ContentTranslator::stage()), once the thing it belongs to has been saved
// A form cannot write these itself, its POST_SUBMIT firing before the root form is validated - so a write there would store a submission about to be refused
#[AsDoctrineListener(event: Events::postFlush)]
class TranslationWriteListener
{
    // store() flushes, which would bring us straight back here
    private bool $writing = false;

    public function __construct(private readonly ContentTranslator $contentTranslator)
    {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->writing) {
            return;
        }

        $pending = $this->contentTranslator->takePending();
        if ([] === $pending) {
            return;
        }

        $this->writing = true;

        try {
            foreach ($pending as [$ownerType, $ownerId, $locale, $values]) {
                $this->contentTranslator->store($ownerType, $ownerId, $locale, $values);
            }
        } finally {
            $this->writing = false;
        }
    }
}
