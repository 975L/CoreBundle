<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Listener\LocaleListener;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocaleListenerTest extends TestCase
{
    /**
     * @param array<string, string> $headers "accept-language" and the like, as a browser sends them
     */
    private function createEvent(array $headers = [], ?string $chosen = null, ?string $routeLocale = null, ?string $asked = null, string $path = '/', ?string $sessionKey = null): RequestEvent
    {
        $request = Request::create($path);
        if (null !== $asked) {
            $request->query->set('_locale', $asked);
        }

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        if (null !== $routeLocale) {
            $request->attributes->set('_locale', $routeLocale);
        }

        if (null !== $chosen) {
            $session = new Session(new MockArraySessionStorage());
            $session->set($sessionKey ?? LocaleListener::SESSION_KEY, $chosen);
            $request->setSession($session);
            // What "hasPreviousSession()" asks for: a session opened before this request, not a fresh one
            $request->cookies->set($session->getName(), $session->getId());
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * The site says nothing of its own, so SiteLocales falls back on what the kernel was given.
     *
     * @param list<string> $locales
     */
    private function siteLocales(array $locales): SiteLocales
    {
        return new SiteLocales($locales, $locales[0] ?? 'en');
    }

    // Reading the site in English is a choice about the content, not about the screens an editor works on: one key for both had the whole back office change language behind them the moment they clicked a flag on the front
    public function testTheFrontChoiceIsNotAnsweredWithInTheBackOffice(): void
    {
        $event = $this->createEvent(['accept-language' => 'fr'], 'en', path: '/management/collection');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // The back office keeps a choice of its own, made with EasyAdmin's own selector, and answers in it
    public function testTheBackOfficeAnswersInItsOwnChoice(): void
    {
        $event = $this->createEvent(['accept-language' => 'fr'], 'en', path: '/management/collection', sessionKey: LocaleListener::SESSION_KEY_MANAGEMENT);

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // A front page whose path merely starts with the back office's is a front page: read as management it would answer in the back office's language, and a flag clicked on it would change the language of the whole back office
    public function testAFrontPathMerelyStartingWithTheBackOfficesIsAnsweredFromTheFront(): void
    {
        $event = $this->createEvent(['accept-language' => 'fr'], 'en', path: '/management-de-projet');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // And a language picked in the back office is kept there, leaving the front reading what it was reading
    public function testALanguagePickedInTheBackOfficeIsKeptUnderItsOwnKey(): void
    {
        $event = $this->createEvent(asked: 'en', chosen: 'fr', path: '/management/collection');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $session = $event->getRequest()->getSession();
        $this->assertSame('en', $session->get(LocaleListener::SESSION_KEY_MANAGEMENT));
        $this->assertSame('fr', $session->get(LocaleListener::SESSION_KEY));
    }

    // The no-regression contract: as long as a site declares a single language - which every existing site does - none of this happens
    public function testASiteDeclaringOneLocaleIsLeftExactlyAsItWas(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);
        $before = $event->getRequest()->getLocale();

        new LocaleListener($this->siteLocales(['fr']), $this->urls())($event);

        $this->assertSame($before, $event->getRequest()->getLocale());
    }

    public function testNoLocaleDeclaredAtAllChangesNothingEither(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);
        $before = $event->getRequest()->getLocale();

        new LocaleListener($this->siteLocales([]), $this->urls())($event);

        $this->assertSame($before, $event->getRequest()->getLocale());
    }

    // What the browser asks for is a preference already expressed: "en-GB" means "en" to a site declaring "en"
    public function testTheBrowsersOwnLanguageIsUsedWhenNothingWasChosen(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // A language no catalogue serves falls back on the first declared, rather than on a locale nothing knows
    public function testALanguageTheSiteDoesNotSpeakFallsBackOnTheFirstDeclared(): void
    {
        $event = $this->createEvent(['accept-language' => 'de-DE,de;q=0.9']);

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // The visitor's own choice comes before what their browser announces
    public function testTheChoiceKeptInSessionBeatsTheBrowser(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'fr');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // A value written in the session by something other than the menu - a session restored from a site that spoke one language more - does not answer in a language the site no longer serves
    public function testAChoiceTheSiteNoLongerDeclaresIsIgnored(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'de');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // A url saying its own language - a localised page - wins, or the same address would answer in two languages depending on the visitor
    public function testARouteCarryingItsOwnLocaleIsLeftAlone(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'en', 'fr');
        $event->getRequest()->setLocale('fr');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // EasyAdmin's language selector appends "?_locale=xx" to the url it is on and reads it back nowhere, so this is what makes it work at all
    public function testALanguageAskedForInTheQueryIsAnsweredIn(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], asked: 'fr');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // And kept, or the choice would last exactly one page - which is also how the front office follows a language picked in the back office
    public function testALanguageAskedForInTheQueryIsKeptForTheNextRequests(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'en', asked: 'fr');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('fr', $event->getRequest()->getSession()->get(LocaleListener::SESSION_KEY));
    }

    // A query parameter is written by whoever wants: a language the site does not declare is dropped rather than trusted
    public function testALanguageAskedForButNotDeclaredIsIgnored(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], asked: 'de');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // Reading a session the visitor does not have starts one: a cookie for every anonymous visitor is not the price of a language menu
    public function testAVisitorWithoutASessionIsNotGivenOne(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertFalse($event->getRequest()->hasSession());
    }

    // A route whose own path says its language moves a visitor who picked another one from the menu to that same route in it, the other query parameters along
    public function testALanguagePickedOnARouteSayingItsOwnMovesToThatRouteInIt(): void
    {
        $event = $this->routeEvent('preview', 'fr', 'en', ['page' => '2']);

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/en/preview/abc?page=2', $response->getTargetUrl());
        $this->assertSame('en', $event->getRequest()->getSession()->get(LocaleListener::SESSION_KEY));
    }

    // The menu's form is sent to the url of its first language: picking that one is kept too, and moves to the url without the query rather than looping on it
    public function testTheLanguageAlreadyReadIsKeptAndDropsTheQuery(): void
    {
        $event = $this->routeEvent('preview', 'fr', 'fr');
        $event->getRequest()->getSession()->set(LocaleListener::SESSION_KEY, 'en');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fr/preview/abc', $response->getTargetUrl());
        $this->assertSame('fr', $event->getRequest()->getSession()->get(LocaleListener::SESSION_KEY));
    }

    // A route Symfony declares per language is moved to its path in the language asked, through its canonical name
    public function testARouteDeclaredPerLanguageMovesToItsPathInTheLanguageAsked(): void
    {
        $event = $this->routeEvent('legal.fr', 'fr', 'en');
        $event->getRequest()->attributes->set('_canonical_route', 'legal');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/legal-notice', $response->getTargetUrl());
    }

    // A "_locale" the route only has as a default cannot be said by its path: following it would redirect to the same page again and again
    public function testALocaleOnlyDefaultedIsNotMoved(): void
    {
        $event = $this->routeEvent('about', 'fr', 'en');

        new LocaleListener($this->siteLocales(['fr', 'en']), $this->urls())($event);

        $this->assertNull($event->getResponse());
    }

    // A localised twin is left to LocalizedRouteNegotiator, its menu linking to the bare url, and a language the route refuses leaves the visitor where they are
    public function testATwinOrARefusedLanguageIsNotMoved(): void
    {
        foreach ([['shop_index_localized', 'en'], ['preview', 'de']] as [$route, $asked]) {
            $event = $this->routeEvent($route, 'fr', $asked);

            new LocaleListener($this->siteLocales(['fr', 'en', 'de']), $this->urls())($event);

            $this->assertNull($event->getResponse(), $route . ' ' . $asked);
        }
    }

    /** @param array<string, string> $query */
    private function routeEvent(string $route, string $routeLocale, string $asked, array $query = []): RequestEvent
    {
        $event = $this->createEvent(routeLocale: $routeLocale, asked: $asked);
        $request = $event->getRequest();
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', ['_locale' => $routeLocale, 'short' => 'abc']);
        foreach ($query as $name => $value) {
            $request->query->set($name, $value);
        }
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $event;
    }

    // "preview" says its language in its own path and accepts "fr" and "en" only, "legal" is declared per language by Symfony, and "about" only has "fr" as a default
    private function urls(): LocalizedUrlGenerator
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = []): string {
                $locale = $parameters['_locale'] ?? null;
                $path = match ($route) {
                    'legal' => ['fr' => '/mentions-legales', 'en' => '/legal-notice'][$locale] ?? throw new InvalidParameterException('_locale'),
                    'about' => '/about' . ('fr' === $locale ? '' : '?_locale=' . $locale),
                    default => \in_array($locale, ['fr', 'en'], true) ? '/' . $locale . '/preview/' . $parameters['short'] : throw new InvalidParameterException('_locale'),
                };
                unset($parameters['_locale'], $parameters['short']);

                return $path . ([] === $parameters ? '' : '?' . http_build_query($parameters));
            }
        );

        return new LocalizedUrlGenerator($router, $this->siteLocales(['fr', 'en']), new RequestStack());
    }
}
