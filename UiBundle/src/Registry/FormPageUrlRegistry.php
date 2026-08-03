<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\FormPageUrlProviderInterface;

class FormPageUrlRegistry
{
    /** @var FormPageUrlProviderInterface[] */
    private array $providers = [];

    // Called once per provider by FormPageUrlProviderPass
    public function addProvider(FormPageUrlProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // The first provider with an answer wins; null means none has a page for that Form, so the caller falls back on the bare "ui_form_submit" route
    public function get(string $formName): ?string
    {
        foreach ($this->providers as $provider) {
            $url = $provider->getFormPageUrl($formName);
            if (null !== $url) {
                return $url;
            }
        }

        return null;
    }
}
