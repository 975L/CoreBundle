<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;

// Shared Doctrine lifecycle wiring for a listener reacting to a Block kind changing (add/edit/remove all delegate to invalidate()) - a subclass only declares which kind it filters on and which cache tag it invalidates. Lives here rather than in SiteBundle, where it started: SocialBundle had written the same wiring by hand for its own singleton blocks
abstract class AbstractBlockCacheInvalidationListener
{
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    abstract protected function invalidate(object $entity): void;
}
