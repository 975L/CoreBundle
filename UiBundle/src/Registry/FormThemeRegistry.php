<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\FormThemeProviderInterface;

class FormThemeRegistry
{
    /** @var FormThemeProviderInterface[] */
    private array $providers = [];

    public function addProvider(FormThemeProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return string[] */
    public function all(): array
    {
        $themes = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getFormThemes() as $theme) {
                if (!in_array($theme, $themes, true)) {
                    $themes[] = $theme;
                }
            }
        }

        return $themes;
    }
}
