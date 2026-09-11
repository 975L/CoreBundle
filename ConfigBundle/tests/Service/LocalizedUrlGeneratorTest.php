<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocalizedUrlGeneratorTest extends TestCase
{
    // A sort link on "/en/shop" leads back into the English shop, where saying path() sent the visitor straight into the writing language
    public function testALinkIsWrittenInTheLanguageThePageAroundItIsReadIn(): void
    {
        $this->assertSame('/en/shop', $this->generator('en')->path('shop_index'));
    }

    // A filter's own query travels along, and so does everything else a link carries
    public function testTheParametersTravelAlong(): void
    {
        $this->assertSame('/en/shop?order=newest', $this->generator('en')->path('shop_index', ['order' => 'newest']));
    }

    // The no-regression contract: the language the site is written in keeps the urls it always had
    public function testTheWritingLanguageIsLeftExactlyAsItWas(): void
    {
        $this->assertSame('/shop', $this->generator(null)->path('shop_index'));
        $this->assertSame('/shop', $this->generator('fr')->path('shop_index'));
    }

    // A basket, a token url, an endpoint: a route declared once is generated as it always was rather than 500-ing every page it appears on
    public function testARouteWithNoLocalisedTwinIsGeneratedAsItAlwaysWas(): void
    {
        $this->assertSame('/shop/basket/display', $this->generator('en')->path('basket_display'));
    }

    // A twin that exists but answers nothing in that language - a product not translated yet - would be a link the visitor lands on a 404 from
    public function testARouteThatLanguageAnswersNothingInKeepsItsBareUrl(): void
    {
        $generator = $this->generator('en');

        $this->assertSame('/shop/products/table-basse', $generator->path('product_display', ['slug' => 'table-basse'], ['fr']));
        $this->assertSame('/en/shop/products/table-basse', $generator->path('product_display', ['slug' => 'table-basse'], ['fr', 'en']));
    }

    // A language menu on a screen with no Page behind it: the same screen offered in each language the site declares, through the bare url that keeps the choice
    public function testAScreenIsOfferedInEveryLanguageTheSiteDeclares(): void
    {
        $this->assertSame(
            ['fr' => '/shop?_locale=fr', 'en' => '/shop?_locale=en'],
            $this->generator('en', 'shop_index_localized', ['_locale' => 'en'])->screenLanguages(),
        );
    }

    // The bare url is what a language menu links to, whichever of the pair is being read
    public function testTheSameLanguagesAreOfferedFromTheBareUrl(): void
    {
        $this->assertSame(
            ['fr' => '/shop?_locale=fr', 'en' => '/shop?_locale=en'],
            $this->generator(null, 'shop_index')->screenLanguages(),
        );
    }

    // A screen answered in one language alone - a back-office url, an endpoint, a token url - offers nothing at all
    public function testAScreenWithNoLocalisedTwinOffersNoLanguage(): void
    {
        $this->assertSame([], $this->generator(null, 'basket_display')->screenLanguages());
    }

    // A site declaring a single language, which is every site until it says otherwise
    public function testASingleLanguageSiteOffersNoLanguage(): void
    {
        $this->assertSame([], $this->generator(null, 'shop_index', [], ['fr'])->screenLanguages());
    }

    /**
     * @param array<string, mixed> $routeParams
     * @param list<string>         $enabledLocales
     */
    private function generator(?string $readingLocale, ?string $route = null, array $routeParams = [], array $enabledLocales = ['fr', 'en']): LocalizedUrlGenerator
    {
        $request = Request::create('/');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

        if (null !== $route) {
            $request->attributes->set('_route', $route);
            $request->attributes->set('_route_params', $routeParams);
        }

        // Only "shop_index" and "product_display" are declared twice; "basket_display" is the route a bundle answers once
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = []): string {
                $path = match ($route) {
                    'shop_index' => '/shop',
                    'shop_index_localized' => '/' . $parameters['_locale'] . '/shop',
                    'product_display' => '/shop/products/' . $parameters['slug'],
                    'product_display_localized' => '/' . $parameters['_locale'] . '/shop/products/' . $parameters['slug'],
                    'basket_display' => '/shop/basket/display',
                    default => throw new RouteNotFoundException($route),
                };

                unset($parameters['_locale'], $parameters['slug']);

                return $path . ([] === $parameters ? '' : '?' . http_build_query($parameters));
            }
        );

        return new LocalizedUrlGenerator($router, new SiteLocales($enabledLocales, 'fr'), new RequestStack([$request]));
    }
}
