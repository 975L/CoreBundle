<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity\Trait;

use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;

// Minimal HasBlocksInterface owner, standing in for a real entity (Page, Article...) - its own file (not inlined in the test class) since src/Tests classes are autoloadable by consuming apps, whose attribute route loader recursively reflects every class under the bundle root
class HasBlocksTraitStub implements HasBlocksInterface
{
    use HasBlocksTrait;

    private ArrayCollection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }
}
