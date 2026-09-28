<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

// Sends an anonymous visitor to the login form in the language of the page they asked for: "/login" has no localised url, so "/en/account" would otherwise fall on it in whatever language the session or the browser says. A route carrying its own "_locale" hands it on as "?_locale=", which LocaleListener reads and keeps; any other route leads to the bare form, as Symfony's own entry point does
class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    // Name of the login route, as scaffolded by this bundle and used across the c975L apps
    private const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Redirects to the login form, with the language the asked route says, if it says one
    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        $locale = $request->attributes->get('_locale');

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE, \is_string($locale) ? ['_locale' => $locale] : []));
    }
}
