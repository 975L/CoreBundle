<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Registry\ScriptRegistry;
use Twig\Attribute\AsTwigFunction;

class ScriptExtension
{
    public function __construct(private readonly ScriptRegistry $registry)
    {
    }

    #[AsTwigFunction('bundle_scripts')]
    public function getBundleScripts(): array
    {
        return $this->registry->all();
    }
}
