<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity\Trait;

use c975L\UiBundle\Entity\Trait\VichMediaTrait;

// Minimal entity using VichMediaTrait, in its own file as HasBlocksTraitStub is
class VichMediaTraitStub
{
    use VichMediaTrait;

    // Not part of the trait itself (Doctrine assigns it on persist) - only needed here to exercise equals() by id
    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }
}
