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
use c975L\ConfigBundle\Service\SiteLocales;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
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

        // A route carrying its own "_locale" has already said which language it serves, and that beats both the session and the browser
        if (null !== $request->attributes->get('_locale')) {
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

    // Which of the two choices this request is answered from: the back office reads its own, and never the front's
    private function sessionKey(Request $request): string
    {
        return DashboardController::isManagementPath($request->getPathInfo())
            ? self::SESSION_KEY_MANAGEMENT
            : self::SESSION_KEY;
    }
}
