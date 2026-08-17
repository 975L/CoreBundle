<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity\Trait;

use c975L\UiBundle\Contract\TrashableInterface;
use c975L\UiBundle\Entity\Trait\TrashableTrait;

// Minimal TrashableInterface entity, standing in for a real one (Page, GalleryCategory...) - its own file (not inlined in the test class) since src/Tests classes are autoloadable by consuming apps, whose attribute route loader recursively reflects every class under the bundle root
class TrashableTraitStub implements TrashableInterface
{
    use TrashableTrait;
}
