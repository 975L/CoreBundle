<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\EmailLayoutProviderInterface;

class EmailLayoutRegistry
{
    /** @var EmailLayoutProviderInterface[] */
    private array $providers = [];

    public function addProvider(EmailLayoutProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // The first registered provider wins; null means none is installed, so the caller falls back
    public function wrap(string $bodyHtml, ?string $locale = null): ?string
    {
        return [] === $this->providers ? null : $this->providers[0]->wrap($bodyHtml, $locale);
    }
}
