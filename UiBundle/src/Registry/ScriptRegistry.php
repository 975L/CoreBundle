<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BundleScriptProviderInterface;

class ScriptRegistry
{
    /** @var BundleScriptProviderInterface[] */
    private array $providers = [];

    public function addProvider(BundleScriptProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return string[] */
    public function all(): array
    {
        $scripts = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getScripts() as $script) {
                if (!in_array($script, $scripts, true)) {
                    $scripts[] = $script;
                }
            }
        }

        return $scripts;
    }
}
