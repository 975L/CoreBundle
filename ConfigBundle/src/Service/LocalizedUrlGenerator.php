<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The url of a route in the language the page around it is being read in, for the one thing an InternalLinkLocalizer cannot reach - a link a template generates rather than one an editor stored: a sort link, a filter and a "see the basket" button all say path('shop_index'), and on "/en/shop" every one of them used to send the visitor straight back into the writing language. The rule is the one a menu item already followed, held here so a template, a menu and a stored link all read a link the same way
class LocalizedUrlGenerator
{
    // What a bundle names the second of the pair, the bare route keeping the name it always had. Public: a controller answering both urls has to tell one from the other (see LocalizedRouteNegotiator::bareRoute)
    public const string LOCALIZED_SUFFIX = '_localized';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteLocales $siteLocales,
        private readonly RequestStack $requestStack,
    ) {
    }

    // The route's own url, or its localised twin's when there is one and the language being read answers for it: "$locales" names the languages the target really answers in, for a twin that exists but 404s where a row is not translated yet, and left null the twin is taken whenever it exists
    /**
     * @param array<string, mixed> $parameters
     * @param list<string>|null    $locales
     */
    public function path(string $route, array $parameters = [], ?array $locales = null): string
    {
        $locale = $this->readingLocale();
        if (null === $locale || (null !== $locales && !\in_array($locale, $locales, true))) {
            return $this->urlGenerator->generate($route, $parameters);
        }

        try {
            return $this->urlGenerator->generate($route . self::LOCALIZED_SUFFIX, $parameters + ['_locale' => $locale]);
        } catch (RoutingExceptionInterface) {
            // No localised twin, or none answering for these parameters: the bare url is what this link has always been
            return $this->urlGenerator->generate($route, $parameters);
        }
    }

    // The screen being read, offered in each language the site declares - what a language menu is made of, empty for a screen answering in one language alone, and not for a screen whose content is translated row by row, only the bundle owning that knowing what it really says. Each url is the bare one carrying "?_locale=xx" rather than the localised url itself: that query is what LocaleListener reads to keep the choice for the rest of the visit, the negotiator moving the visitor on to "/xx/..." from there
    /** @return array<string, string> locale => url */
    public function screenLanguages(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route');
        if (!\is_string($route) || '' === $route || !$this->siteLocales->isMultilingual()) {
            return [];
        }

        // The bare route is the one a language menu links to, whichever of the pair is being read
        $bare = str_ends_with($route, self::LOCALIZED_SUFFIX) ? substr($route, 0, -\strlen(self::LOCALIZED_SUFFIX)) : $route;

        $parameters = (array) $request->attributes->get('_route_params');
        unset($parameters['_locale']);

        // Probed with a language the twin really accepts: its "_locale" requirement holds the languages the site declares beside the one it is written in, and the writing language is refused there (see c975LConfigBundle::declareLocalesPattern)
        $spoken = array_values(array_filter($this->siteLocales->all(), fn (string $locale): bool => $locale !== $this->siteLocales->getDefaultLocale()));
        if ([] === $spoken) {
            return [];
        }

        try {
            // Asked of the twin rather than of the bare route: it is its existence that says this screen is answered in several languages at all
            $this->urlGenerator->generate($bare . self::LOCALIZED_SUFFIX, $parameters + ['_locale' => $spoken[0]]);
            $path = $this->urlGenerator->generate($bare, $parameters);
        } catch (RoutingExceptionInterface) {
            return [];
        }

        $languages = [];
        foreach ($this->siteLocales->all() as $locale) {
            $languages[$locale] = $path . (str_contains($path, '?') ? '&' : '?') . '_locale=' . $locale;
        }

        return $languages;
    }

    // The language being read, when it is one the site declares besides the one it was written in. The route attribute rather than getLocale(), which a controller switches back for the duration of the render
    private function readingLocale(): ?string
    {
        $locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');

        return \is_string($locale) && '' !== $locale && $locale !== $this->siteLocales->getDefaultLocale() ? $locale : null;
    }
}
