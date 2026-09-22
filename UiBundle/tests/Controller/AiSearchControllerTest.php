<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Contract\AiSearchCardProviderInterface;
use c975L\UiBundle\Controller\AiSearchController;
use c975L\UiBundle\Registry\AiSearchCardRegistry;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

// Every question may cost the site's own key: what reaches AiSiteSearch::ask() has come from a page of this site, within both ceilings and at a sensible length
#[AllowMockObjectsWithoutExpectations]
class AiSearchControllerTest extends TestCase
{
    private const array RESULT = ['answer' => 'Du lundi au vendredi.', 'sources' => [['url' => 'https://site.example/horaires', 'title' => 'Horaires']], 'found' => true];

    public function testAQuestionIsAnsweredAndNeverCached(): void
    {
        $search = $this->search();
        $search->expects($this->once())->method('ask')->with('Quels horaires ?', 'fr')->willReturn(self::RESULT);

        $response = $this->controller($search)->ask($this->request(['question' => 'Quels horaires ?']));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(self::RESULT + ['cards' => ''], json_decode((string) $response->getContent(), true));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    // A source a bundle draws as its own card leaves the plain links, and the card is rendered on the answer rather than stored with it
    public function testASourceABundleDrawsAsACardLeavesThePlainLinks(): void
    {
        $search = $this->search();
        $search->method('ask')->willReturn(self::RESULT);

        $provider = $this->createStub(AiSearchCardProviderInterface::class);
        $provider->method('renderCards')->willReturn(['https://site.example/horaires' => '<article>Horaires</article>']);
        $registry = new AiSearchCardRegistry();
        $registry->addProvider($provider);

        $data = json_decode((string) new AiSearchController($search, $registry, new RateLimiterGuard(), new SiteLocales(['fr', 'en'], 'fr'), $this->factory(true), $this->factory(true))->ask($this->request(['question' => 'Quels horaires ?']))->getContent(), true);

        $this->assertSame([], $data['sources']);
        $this->assertSame('<article>Horaires</article>', $data['cards']);
    }

    public function testAQuestionFromAnotherOriginIsTurnedDown(): void
    {
        $request = $this->request(['question' => 'Quels horaires ?']);
        $request->headers->set('Origin', 'https://elsewhere.example');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->ask($request)->getStatusCode());
    }

    public function testASearchSwitchedOffAnswersUnavailable(): void
    {
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $this->controller($this->search(false))->ask($this->request(['question' => 'Quels horaires ?']))->getStatusCode());
    }

    public function testAQuestionTooShortOrTooLongIsRefused(): void
    {
        $search = $this->search();
        $search->expects($this->never())->method('ask');
        $controller = $this->controller($search);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $controller->ask($this->request(['question' => 'a']))->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $controller->ask($this->request(['question' => str_repeat('a', AiSiteSearch::MAX_LENGTH + 1)]))->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $controller->ask($this->request(['question' => ['a', 'b']]))->getStatusCode());
    }

    // The site-wide ceiling refuses on its own, whatever the caller's own bucket says
    public function testTheSiteWideCeilingRefusesOnItsOwn(): void
    {
        $search = $this->search();
        $search->expects($this->never())->method('ask');

        $response = new AiSearchController($search, new AiSearchCardRegistry(), new RateLimiterGuard(), new SiteLocales(['fr', 'en'], 'fr'), $this->factory(true), $this->factory(false))->ask($this->request(['question' => 'Quels horaires ?']));

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function testAProviderFailureAnswersUnavailable(): void
    {
        $search = $this->search();
        $search->method('ask')->willReturn(null);

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $this->controller($search)->ask($this->request(['question' => 'Quels horaires ?']))->getStatusCode());
    }

    // Searched in the language of the page it was asked on, and never in one the site doesn't offer
    public function testTheQuestionIsSearchedInThePageLocale(): void
    {
        $locales = [];
        $search = $this->search();
        $search->method('ask')->willReturnCallback(function (string $question, string $locale) use (&$locales): array {
            $locales[] = $locale;

            return self::RESULT;
        });

        $controller = $this->controller($search);
        $controller->ask($this->request(['question' => 'Opening hours?', 'locale' => 'en']));
        $controller->ask($this->request(['question' => 'Öffnungszeiten?', 'locale' => 'de']));

        $this->assertSame(['en', 'fr'], $locales);
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/ai-search', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', 'http://localhost');
        $request->setLocale('fr');

        return $request;
    }

    private function search(bool $enabled = true): AiSiteSearch
    {
        $search = $this->createMock(AiSiteSearch::class);
        $search->method('isEnabled')->willReturn($enabled);

        return $search;
    }

    private function factory(bool $accepted): RateLimiterFactoryInterface
    {
        $limit = $this->createMock(RateLimit::class);
        $limit->method('isAccepted')->willReturn($accepted);
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limit);
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }

    private function controller(?AiSiteSearch $search = null): AiSearchController
    {
        if (null === $search) {
            $search = $this->search();
            $search->method('ask')->willReturn(self::RESULT);
        }

        return new AiSearchController($search, new AiSearchCardRegistry(), new RateLimiterGuard(), new SiteLocales(['fr', 'en'], 'fr'), $this->factory(true), $this->factory(true));
    }
}
