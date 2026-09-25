<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Listener;

use c975L\ConfigBundle\Controller\Management\DashboardController;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

// The language a request is answered in when the site declares more than one (see SiteLocales): the visitor's choice kept in session, then what their browser asks for
// Priority 20, above Symfony's LocaleAwareListener (15) which hands the locale to the translator, and below RouterListener (32) so the route guard below still sees its attribute
#[AsEventListener(event: 'kernel.request', priority: 20)]
class LocaleListener
{
    // Where the chosen language is kept, and where Symfony looks for it on its own
    public const string SESSION_KEY = '_locale';

    // The back office keeps a choice of its own, under a key of its own: reading the site in English is a choice about the content, not about the screens an editor works on, and one key for both had the whole back office change language behind them the moment they clicked a flag on the front
    public const string SESSION_KEY_MANAGEMENT = '_locale_management';

    public function __construct(
        private readonly SiteLocales $siteLocales,
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->siteLocales->isMultilingual()) {
            return;
        }

        $request = $event->getRequest();
        $locales = $this->siteLocales->all();
        $sessionKey = $this->sessionKey($request);

        // A route carrying its own "_locale" has already said which language it serves, and that beats both the session and the browser - unless another one was just picked from the menu, which moves the visitor to that same route in the language asked
        if (null !== $request->attributes->get('_locale')) {
            $redirect = $this->redirectToAskedLanguage($request, $locales, $sessionKey);
            if (null !== $redirect) {
                $event->setResponse($redirect);
            }

            return;
        }

        // A language just picked from a menu beats what is already kept: EasyAdmin's selector only appends "?_locale=xx" and nothing reads it back, so it is read and kept here
        $asked = $request->query->get('_locale');
        if (\is_string($asked) && \in_array($asked, $locales, true)) {
            if ($request->hasSession()) {
                $request->getSession()->set($sessionKey, $asked);
            }

            $request->setLocale($asked);

            return;
        }

        // hasPreviousSession() rather than getSession(), which would start a session - and a cookie - for every anonymous visitor of the front
        $chosen = $request->hasPreviousSession() ? $request->getSession()->get($sessionKey) : null;

        // Falls back on what the browser asks for and, failing any match, on the first locale declared
        $request->setLocale(\is_string($chosen) && \in_array($chosen, $locales, true)
            ? $chosen
            : $request->getPreferredLanguage($locales));
    }

    // The same route in the language picked from the menu ("?_locale=xx"), for a route whose own path says its language - "/fr/preview/abc" - which the menu's single form cannot link to one by one. The language already read moves too, to the url without the query, so the choice is kept whichever entry the form was sent to. A localised twin is left out, its menu linking to the bare url that LocalizedRouteNegotiator moves on, and a language the route does not accept leaves the visitor where they are
    /** @param list<string> $locales */
    private function redirectToAskedLanguage(Request $request, array $locales, string $sessionKey): ?RedirectResponse
    {
        $asked = $request->query->get('_locale');
        $route = $request->attributes->get('_route');
        if (
            !$request->isMethodSafe()
            || !\is_string($asked) || !\in_array($asked, $locales, true)
            || !\is_string($route) || str_ends_with($route, LocalizedUrlGenerator::LOCALIZED_SUFFIX)
        ) {
            return null;
        }

        $query = $request->query->all();
        unset($query['_locale']);

        $url = $this->localizedUrlGenerator->sameRouteIn($request, $asked, $query);
        if (null === $url) {
            return null;
        }

        if ($request->hasSession()) {
            $request->getSession()->set($sessionKey, $asked);
        }

        return new RedirectResponse($url);
    }

    // Which of the two choices this request is answered from: the back office reads its own, and never the front's
    private function sessionKey(Request $request): string
    {
        return DashboardController::isManagementPath($request->getPathInfo())
            ? self::SESSION_KEY_MANAGEMENT
            : self::SESSION_KEY;
    }
}
