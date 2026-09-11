<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\SiteLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;

class LocalizedRouteNegotiatorTest extends TestCase
{
    // A localised url answers only for what that language really says: served otherwise it would be the writing language's words under another language's address
    public function testALocalisedUrlIsRefusedForALanguageTheThingSaysNothingIn(): void
    {
        $request = Request::create('/es/shop');
        $request->attributes->set('_locale', 'es');

        $this->assertFalse($this->negotiator()->isTranslated($request, ['fr', 'en']));
        $this->assertTrue($this->negotiator()->isTranslated($request, ['fr', 'es']));
    }

    // A bare url says nothing about a language, so there is nothing to refuse there
    public function testABareUrlIsNeverRefused(): void
    {
        $this->assertTrue($this->negotiator()->isTranslated(Request::create('/shop'), ['fr']));
    }

    // The language the browser announced, read exactly as LocaleListener read it
    public function testAVisitorWhoseBrowserAsksForATranslatedLanguageIsSentToItsUrl(): void
    {
        $request = Request::create('/shop');
        $request->headers->set('Accept-Language', 'en');
        $request->setLocale('en');

        $redirect = $this->negotiator()->redirectToAskedLanguage($request, ['fr', 'en'], 'shop_index');

        $this->assertNotNull($redirect);
        $this->assertStringContainsString('shop_index_localized', $redirect->getTargetUrl());
        $this->assertStringContainsString('_locale=en', $redirect->getTargetUrl());
    }

    // A campaign's "utm_source" would otherwise be dropped by the redirect
    public function testTheQueryStringTravelsWithTheRedirect(): void
    {
        $request = Request::create('/shop?utm_source=newsletter');
        $request->headers->set('Accept-Language', 'en');
        $request->setLocale('en');

        $redirect = $this->negotiator()->redirectToAskedLanguage($request, ['fr', 'en'], 'shop_index');

        $this->assertStringContainsString('utm_source=newsletter', (string) $redirect?->getTargetUrl());
    }

    // A visitor who asked for none of the three has no business being moved off the url they requested - and the writing language is put back on the request, whatever LocaleListener had handed the translator
    public function testAVisitorWhoAskedForNothingStaysWhereTheyAreInTheWritingLanguage(): void
    {
        // Announcing the writing language while the request carries another: Request::create() announces "en-us,en" of its own accord, which would otherwise read as a language asked for
        $request = Request::create('/shop');
        $request->headers->set('Accept-Language', 'fr');
        $request->setLocale('en');

        $this->assertNull($this->negotiator()->redirectToAskedLanguage($request, ['fr', 'en'], 'shop_index'));
        $this->assertSame('fr', $request->getLocale());
    }

    // Nothing to redirect to when that language says nothing: it would only hand the visitor the French screen under lang="en"
    public function testALanguageTheThingSaysNothingInIsNoDestination(): void
    {
        $request = Request::create('/shop');
        $request->headers->set('Accept-Language', 'en');
        $request->setLocale('en');

        $this->assertNull($this->negotiator()->redirectToAskedLanguage($request, ['fr'], 'shop_index'));
    }

    // A url that already says its language in its path has nothing to negotiate
    public function testALocalisedUrlIsNeverRedirected(): void
    {
        $request = Request::create('/en/shop');
        $request->attributes->set('_locale', 'en');
        $request->setLocale('en');

        $this->assertNull($this->negotiator()->redirectToAskedLanguage($request, ['fr', 'en'], 'shop_index'));
    }

    // The language picked from a menu lands in the query, and a visitor arriving without a session cookie has nothing else
    public function testTheLanguagePickedFromAMenuIsAnsweredIn(): void
    {
        $request = Request::create('/shop?_locale=en');
        $request->headers->set('Accept-Language', 'fr');
        $request->setLocale('en');

        $this->assertNotNull($this->negotiator()->redirectToAskedLanguage($request, ['fr', 'en'], 'shop_index'));
    }

    // And the one picked earlier, which LocaleListener keeps in session
    public function testTheLanguagePickedEarlierIsAnsweredIn(): void
    {
        $request = Request::create('/shop');
        $request->headers->set('Accept-Language', 'fr');
        $request->setLocale('en');

        $session = new Session(new MockArraySessionStorage());
        $session->set('_locale', 'en');
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());

        $this->assertNotNull($this->negotiator()->redirectToAskedLanguage($request, ['fr', 'en'], 'shop_index'));
    }

    // A bare url is answered in one language or redirected to another depending on what the browser announced, which a shared cache has to be told
    public function testABareUrlVariesOnWhatTheBrowserAnnounced(): void
    {
        $response = $this->negotiator()->vary(Request::create('/shop'), new Response());

        $this->assertSame(['Accept-Language'], $response->getVary());
    }

    // A localised url says its language in itself and varies on nothing; nor does any url of a site declaring one language
    public function testALocalisedUrlVariesOnNothing(): void
    {
        $request = Request::create('/en/shop');
        $request->attributes->set('_locale', 'en');

        $this->assertSame([], $this->negotiator()->vary($request, new Response())->getVary());
        $this->assertSame([], $this->negotiator(['fr'])->vary(Request::create('/shop'), new Response())->getVary());
    }

    /** @param list<string> $enabledLocales */
    private function negotiator(array $enabledLocales = ['fr', 'en']): LocalizedRouteNegotiator
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => '/' . $name . '?' . http_build_query($parameters)
        );

        return new LocalizedRouteNegotiator(new SiteLocales($enabledLocales, 'fr'), new LocaleSwitcher('fr', []), $router);
    }
}
