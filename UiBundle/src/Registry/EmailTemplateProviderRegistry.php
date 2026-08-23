<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;

// Every transactional e-mail the installed bundles say a site should be able to send
class EmailTemplateProviderRegistry
{
    /** @var EmailTemplateProviderInterface[] */
    private array $providers = [];

    public function addProvider(EmailTemplateProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<string, array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>>
     */
    public function getDeclaredTemplates(): array
    {
        $declared = [];
        foreach ($this->providers as $provider) {
            // A later provider wins on a name an earlier one already declared, which is how an app overrides what a bundle ships without the bundle knowing
            $declared = array_replace($declared, $provider->getEmailTemplates());
        }

        ksort($declared);

        return $declared;
    }
}
