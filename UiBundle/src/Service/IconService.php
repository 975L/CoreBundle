<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class IconService implements IconServiceInterface
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getIcons(): array
    {
        $icons = [];
        $publicDir = $this->projectDir . '/public';

        foreach (glob($publicDir . '/bundles/*/icons/*.svg') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $icons[$name] = 'bundles/' . basename(dirname($file, 2)) . '/icons/' . basename($file);
        }

        foreach (glob($publicDir . '/icons/*.svg') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $icons[$name] = 'icons/' . basename($file);
        }

        ksort($icons);

        return $icons;
    }
}
