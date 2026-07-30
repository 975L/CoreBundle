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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ScriptExtension extends AbstractExtension
{
    public function __construct(private ScriptRegistry $registry)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('bundle_scripts', [$this, 'getBundleScripts']),
        ];
    }

    public function getBundleScripts(): array
    {
        return $this->registry->all();
    }
}
