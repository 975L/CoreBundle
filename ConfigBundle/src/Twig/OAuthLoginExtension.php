<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Security\OAuthLoginProviderInterface;
use c975L\ConfigBundle\Security\OAuthLoginProviderRegistry;
use Twig\Attribute\AsTwigFunction;

// Hands the login providers this site enabled to the Security/OAuthLogin component.
//
// A function rather than a component class: the components of this ecosystem are anonymous templates, rendered either from a template or by the Block system, so none of them can be given a dependency. What providers to show comes from the configuration and from no caller, which is exactly what a Twig function is for - form_url(), a few lines above in the same login page, reads its Page the same way
class OAuthLoginExtension
{
    public function __construct(
        private readonly OAuthLoginProviderRegistry $providerRegistry,
    ) {
    }

    /**
     * The providers holding credentials, empty on a site that configured none - which is how every site starts.
     *
     * @return array<OAuthLoginProviderInterface>
     */
    #[AsTwigFunction('oauth_login_providers')]
    public function getOAuthLoginProviders(): array
    {
        return $this->providerRegistry->enabled();
    }
}
