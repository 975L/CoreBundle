<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;

// The three things a controller answering both "/shop" and "/en/shop" has to do, said once for every bundle declaring such a pair - refuse a language the thing says nothing in, move a visitor who asked for one, tell the caches a bare url varies on what the browser announced - with the languages passed in rather than read off an entity: a page is translated beside itself, a book is a row per language, a product will be something else again, and what they have in common is the list, not how it is arrived at (see SiteBundle's PageController, the original of all three)
class LocalizedRouteNegotiator
{
    public function __construct(
        private readonly SiteLocales $siteLocales,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Refuses a localised url the thing says nothing in: it would otherwise answer the writing language's words under another language's address, duplicate content under a lying hreflang
    /** @param list<string> $translatedLocales */
    public function isTranslated(Request $request, array $translatedLocales): bool
    {
        $locale = $request->attributes->get('_locale');

        return !\is_string($locale) || '' === $locale || \in_array($locale, $translatedLocales, true);
    }

    // Where a visitor asking for another language should be sent from a bare url, and null when they should stay: only a language they actually asked for counts - the one the browser announced, the one just picked from the menu (which lands in the query, a first click having no session yet), or the one picked earlier and kept under Symfony's "_locale" session key. Announcing none of the three they stay, a crawler having no business being moved off the url it requested, and staying puts the writing language back on the request, a bare url being the writing language's own
    /**
     * @param list<string>         $translatedLocales
     * @param array<string, mixed> $parameters
     */
    public function redirectToAskedLanguage(Request $request, array $translatedLocales, string $route, array $parameters = []): ?RedirectResponse
    {
        if (null !== $request->attributes->get('_locale')) {
            return null;
        }

        $locale = $request->getLocale();

        $asked = $locale === $request->getPreferredLanguage($this->siteLocales->all())
            || $locale === $request->query->get('_locale')
            || ($request->hasPreviousSession() && $locale === $request->getSession()->get('_locale'));

        if ($asked && $locale !== $this->siteLocales->getDefaultLocale() && \in_array($locale, $translatedLocales, true)) {
            // The query string goes along: a campaign's "utm_source" or a block's own filter would otherwise be dropped by the redirect. The route's own parameters come first, and the language right after them, so a "?page=" or a "?_locale=" of the visitor's own making is absorbed by the key collision rather than sending them somewhere else
            return new RedirectResponse($this->urlGenerator->generate($route . '_localized', $parameters + ['_locale' => $locale] + $request->query->all()));
        }

        $this->localeSwitcher->setLocale($this->siteLocales->getDefaultLocale());
        $request->setLocale($this->siteLocales->getDefaultLocale());

        return null;
    }

    // The route being served named as the bundle declared it, whichever of the pair answered: a controller comparing the current route against one of its own - a canonical url, a redirect onto another of its screens - would otherwise stop recognising itself the day the pair was declared
    public function bareRoute(Request $request): ?string
    {
        $route = $request->attributes->get('_route');
        if (!\is_string($route) || '' === $route) {
            return null;
        }

        return str_ends_with($route, LocalizedUrlGenerator::LOCALIZED_SUFFIX)
            ? substr($route, 0, -\strlen(LocalizedUrlGenerator::LOCALIZED_SUFFIX))
            : $route;
    }

    // A bare url is answered in the writing language or redirected, and which of the two depends on what the browser announced: a shared cache holding one answer for every visitor has to be told. A localised url says its language in itself and varies on nothing
    public function vary(Request $request, Response $response): Response
    {
        if (null === $request->attributes->get('_locale') && $this->siteLocales->isMultilingual()) {
            $response->setVary('Accept-Language', false);
        }

        return $response;
    }
}
