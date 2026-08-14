<?php

/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Attribute\AsTwigFunction;

class AssetExists
{
    public function __construct(private readonly ConfigServiceInterface $configService)
    {
    }

    // Checks if the template exists
    #[AsTwigFunction('asset_exists')]
    public function assetExists($asset)
    {
        $root = $this->configService->getContainerParameter('kernel.project_dir');

        return is_file($root . '/public/' . $asset) || is_file($root . '/assets/' . $asset);
    }
}
