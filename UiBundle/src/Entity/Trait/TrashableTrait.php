<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

// Carries the flag itself, mapping included, unlike HasBlocksTrait leaving $blocks to the entity: a relation's mapping differs at every use, a boolean column's never does
// The flag alone is the whole mechanism, and that is the point - a delete that writes "true" here fires no cascade and no file listener, where the remove() it replaces fired both. What restores an entity is writing "false" back, so nothing has to be kept aside to restore it from
trait TrashableTrait
{
    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }
}
