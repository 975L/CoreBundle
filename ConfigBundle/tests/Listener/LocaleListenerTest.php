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
use c975L\ConfigBundle\Service\SiteLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LocaleListenerTest extends TestCase
{
    /**
     * @param array<string, string> $headers "accept-language" and the like, as a browser sends them
     */
    private function createEvent(array $headers = [], ?string $chosen = null, ?string $routeLocale = null, ?string $asked = null): RequestEvent
    {
        $request = new Request();
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
            $session->set(LocaleListener::SESSION_KEY, $chosen);
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

    // The no-regression contract: as long as a site declares a single language - which every existing site does - none of this happens
    public function testASiteDeclaringOneLocaleIsLeftExactlyAsItWas(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);
        $before = $event->getRequest()->getLocale();

        new LocaleListener($this->siteLocales(['fr']))($event);

        $this->assertSame($before, $event->getRequest()->getLocale());
    }

    public function testNoLocaleDeclaredAtAllChangesNothingEither(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);
        $before = $event->getRequest()->getLocale();

        new LocaleListener($this->siteLocales([]))($event);

        $this->assertSame($before, $event->getRequest()->getLocale());
    }

    // What the browser asks for is a preference already expressed: "en-GB" means "en" to a site declaring "en"
    public function testTheBrowsersOwnLanguageIsUsedWhenNothingWasChosen(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // A language no catalogue serves falls back on the first declared, rather than on a locale nothing knows
    public function testALanguageTheSiteDoesNotSpeakFallsBackOnTheFirstDeclared(): void
    {
        $event = $this->createEvent(['accept-language' => 'de-DE,de;q=0.9']);

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // The visitor's own choice comes before what their browser announces
    public function testTheChoiceKeptInSessionBeatsTheBrowser(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'fr');

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // A value written in the session by something other than the menu - a session restored from a site that spoke one language more - does not answer in a language the site no longer serves
    public function testAChoiceTheSiteNoLongerDeclaresIsIgnored(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'de');

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // A url saying its own language - a localised page - wins, or the same address would answer in two languages depending on the visitor
    public function testARouteCarryingItsOwnLocaleIsLeftAlone(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'en', 'fr');
        $event->getRequest()->setLocale('fr');

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // EasyAdmin's language selector appends "?_locale=xx" to the url it is on and reads it back nowhere, so this is what makes it work at all
    public function testALanguageAskedForInTheQueryIsAnsweredIn(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], asked: 'fr');

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('fr', $event->getRequest()->getLocale());
    }

    // And kept, or the choice would last exactly one page - which is also how the front office follows a language picked in the back office
    public function testALanguageAskedForInTheQueryIsKeptForTheNextRequests(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], 'en', asked: 'fr');

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('fr', $event->getRequest()->getSession()->get(LocaleListener::SESSION_KEY));
    }

    // A query parameter is written by whoever wants: a language the site does not declare is dropped rather than trusted
    public function testALanguageAskedForButNotDeclaredIsIgnored(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9'], asked: 'de');

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertSame('en', $event->getRequest()->getLocale());
    }

    // Reading a session the visitor does not have starts one: a cookie for every anonymous visitor is not the price of a language menu
    public function testAVisitorWithoutASessionIsNotGivenOne(): void
    {
        $event = $this->createEvent(['accept-language' => 'en-GB,en;q=0.9']);

        new LocaleListener($this->siteLocales(['fr', 'en']))($event);

        $this->assertFalse($event->getRequest()->hasSession());
    }
}
