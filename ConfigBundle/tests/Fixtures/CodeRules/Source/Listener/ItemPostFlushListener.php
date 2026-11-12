<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

// Good: postFlush, a comment naming Events::preFlush being no listener
#[AsDoctrineListener(event: Events::postFlush)]
class ItemPostFlushListener
{
    public function postFlush(): void
    {
    }
}
