<?php

/*
 * (c) 2019: 975L <contact@975l.com>
 * (c) 2019: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Attribute\AsTwigFunction;

class TemplateExists
{
    public function __construct(private readonly ConfigServiceInterface $configService)
    {
    }

    // Checks if the template exists.
    #[AsTwigFunction('template_exists')]
    public function templateExists($template)
    {
        $root = $this->configService->getContainerParameter('kernel.project_dir');

        return is_file($root . '/templates/' . $template);
    }
}
