<?php

/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Attribute\AsTwigFunction;

class ConfigParamExtension
{
    public function __construct(
        private readonly ParameterBagInterface $params,
    ) {
    }

    #[AsTwigFunction('configParam')]
    public function getConfigParam(string $slug): mixed
    {
        return $this->params->get($slug);
    }
}
