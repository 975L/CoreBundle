<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\BlockFixtureProviderInterface;
use c975L\UiBundle\Registry\BlockFixtureRegistry;

class BlockFixtureProviderPass extends AbstractProviderPass
{
    public function __construct()
    {
        parent::__construct(BlockFixtureProviderInterface::class, BlockFixtureRegistry::class);
    }
}
