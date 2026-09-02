<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Service\FormSeeder;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

// Writes the wording a seeded form carries in the site's other languages, once the flush that saved it has given its fields their ids (see FormSeeder::queueTranslations)
// The seeder never flushes - the caller decides when, a batch of seeds being one transaction - so this is the only place that knows the ids exist
#[AsDoctrineListener(event: Events::postFlush)]
class SeededTranslationWriteListener
{
    // store() flushes, which would bring us straight back here
    private bool $writing = false;

    public function __construct(private readonly FormSeeder $formSeeder)
    {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->writing) {
            return;
        }

        $this->writing = true;

        try {
            $this->formSeeder->writeQueuedTranslations();
        } finally {
            $this->writing = false;
        }
    }
}
