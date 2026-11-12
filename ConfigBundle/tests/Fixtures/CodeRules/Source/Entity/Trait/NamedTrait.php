<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

// A trait's columns are checked once, on the trait: Item using it is not reported for them
trait NamedTrait
{
    // Bad: no Length
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $nickname = null;
}
