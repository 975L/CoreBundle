<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Service\TranslationCopier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

// Writes the translations a duplicator carried over (see TranslationCopier::copy()), once the flush that saved the copy has given it its id
#[AsDoctrineListener(event: Events::postFlush)]
class TranslationCopyListener
{
    // write() flushes, which would bring us straight back here
    private bool $writing = false;

    public function __construct(private readonly TranslationCopier $translationCopier)
    {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->writing) {
            return;
        }

        $this->writing = true;

        try {
            $this->translationCopier->write();
        } finally {
            $this->writing = false;
        }
    }
}
