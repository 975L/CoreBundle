<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\FavoriteController;
use c975L\UiBundle\Service\FavoriteService;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Twig\Environment;

// The routes take no csrf token, so what stands in its place is checked here: a json body (which sends a cross-origin caller through a preflight nothing answers) and an origin that is this site's own. Neither may make the server open a session - a Set-Cookie would be kept by the shared cache the page carrying the heart sits in
#[AllowMockObjectsWithoutExpectations]
class FavoriteControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private const string TOKEN = '0123456789abcdef0123456789abcdef';

    public function testAThingIsPutAsideAndAnsweredWithTheNewState(): void
    {
        $service = $this->createMock(FavoriteService::class);
        $service->method('resolveHolder')->willReturn(self::TOKEN);
        $service->expects($this->once())
            ->method('toggle')
            ->with('shop_product', 39, self::TOKEN)
            ->willReturn(['favorited' => true, 'count' => 3])
        ;

        $response = $this->controller($service)->toggle('shop_product', 39, $this->request(['token' => self::TOKEN]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['favorited' => true, 'count' => 3], json_decode((string) $response->getContent(), true));
    }

    // A visitor who signed in since their last click brings their browser's list with them, before anything is read under either key
    public function testTheBrowsersListIsHandedOverBeforeTheHolderIsResolved(): void
    {
        $service = $this->createMock(FavoriteService::class);
        $service->expects($this->once())->method('merge')->with(self::TOKEN);
        $service->method('resolveHolder')->willReturn('u7');
        $service->method('toggle')->willReturn(['favorited' => true, 'count' => 1]);

        $this->controller($service)->toggle('shop_product', 39, $this->request(['token' => self::TOKEN]));
    }

    // One visitor's own list must never be kept by anything between them and the server, the page it was read from being public
    public function testTheAnswerIsNeverCached(): void
    {
        $response = $this->controller()->toggle('shop_product', 39, $this->request(['token' => self::TOKEN]));

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testAClickFromAnotherOriginIsTurnedDown(): void
    {
        $request = $this->request(['token' => self::TOKEN]);
        $request->headers->set('Origin', 'https://elsewhere.example');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->toggle('shop_product', 39, $request)->getStatusCode());
    }

    // A form post carries no json content type, and is exactly what a browser would send cross-origin without asking anyone first
    public function testAClickThatIsNotJsonIsTurnedDown(): void
    {
        $request = $this->request(['token' => self::TOKEN]);
        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->toggle('shop_product', 39, $request)->getStatusCode());
    }

    public function testAClickWithNeitherOriginNorRefererIsTurnedDown(): void
    {
        $request = $this->request(['token' => self::TOKEN]);
        $request->headers->remove('Origin');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->toggle('shop_product', 39, $request)->getStatusCode());
    }

    public function testARefererOfThisSiteStandsInForAMissingOrigin(): void
    {
        $request = $this->request(['token' => self::TOKEN]);
        $request->headers->remove('Origin');
        $request->headers->set('Referer', 'http://localhost/boutique/le-doudou');

        $this->assertSame(Response::HTTP_OK, $this->controller()->toggle('shop_product', 39, $request)->getStatusCode());
    }

    // A host this one only starts with is another host: "http://localhost.evil.example" must not pass for "http://localhost"
    public function testARefererOnALookalikeHostIsTurnedDown(): void
    {
        $request = $this->request(['token' => self::TOKEN]);
        $request->headers->remove('Origin');
        $request->headers->set('Referer', 'http://localhost.evil.example/boutique/le-doudou');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->toggle('shop_product', 39, $request)->getStatusCode());
    }

    // No usable token and no account: the entry is refused rather than filed under a key the server invented
    public function testAClickWithNoUsableHolderIsRefused(): void
    {
        $service = $this->createMock(FavoriteService::class);
        $service->method('resolveHolder')->willReturn(null);
        $service->expects($this->never())->method('toggle');

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->controller($service)->toggle('shop_product', 39, $this->request([]))->getStatusCode()
        );
    }

    public function testAVisitorOverTheLimitIsTurnedDownWithoutTheEntryBeingWritten(): void
    {
        $service = $this->createMock(FavoriteService::class);
        $service->method('resolveHolder')->willReturn(self::TOKEN);
        $service->expects($this->never())->method('toggle');

        $response = $this->controller($service, accepted: false)->toggle('shop_product', 39, $this->request(['token' => self::TOKEN]));

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function testTheListComesBackRenderedWithTheKeysEveryHeartRepaintsItselfFrom(): void
    {
        $service = $this->createMock(FavoriteService::class);
        $service->method('resolveHolder')->willReturn('u7');
        $service->method('list')->willReturn([['ownerType' => 'book', 'ownerId' => 7, 'item' => new \stdClass()]]);
        $service->method('keys')->willReturn(['book:7']);

        $response = $this->controller($service)->list($this->request(['token' => self::TOKEN]));

        $this->assertSame(
            ['html' => '<div class="cards"></div>', 'keys' => ['book:7'], 'count' => 1],
            json_decode((string) $response->getContent(), true)
        );
    }

    // No token and not signed in: an empty list rather than an error, which is exactly what this visitor has
    public function testAListAskedForWithNoHolderIsEmptyRatherThanRefused(): void
    {
        $service = $this->createMock(FavoriteService::class);
        $service->method('resolveHolder')->willReturn(null);
        $service->expects($this->never())->method('list');

        $response = $this->controller($service)->list($this->request([]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['html' => '', 'keys' => [], 'count' => 0], json_decode((string) $response->getContent(), true));
    }

    public function testTheListIsNeverCachedEither(): void
    {
        $response = $this->controller()->list($this->request(['token' => self::TOKEN]));

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testAListAskedForFromAnotherOriginIsTurnedDown(): void
    {
        $request = $this->request(['token' => self::TOKEN]);
        $request->headers->set('Origin', 'https://elsewhere.example');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->list($request)->getStatusCode());
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/favorites/list', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', 'http://localhost');

        return $request;
    }

    private function controller(?FavoriteService $service = null, bool $accepted = true): FavoriteController
    {
        if (null === $service) {
            $service = $this->createMock(FavoriteService::class);
            $service->method('resolveHolder')->willReturn(self::TOKEN);
            $service->method('toggle')->willReturn(['favorited' => true, 'count' => 1]);
            $service->method('list')->willReturn([]);
            $service->method('keys')->willReturn([]);
        }

        $limit = $this->createMock(RateLimit::class);
        $limit->method('isAccepted')->willReturn($accepted);
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limit);
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<div class="cards"></div>');

        $controller = new FavoriteController($service, new RateLimiterGuard(), $factory);
        $controller->setContainer($this->createContainer(['twig' => $twig]));

        return $controller;
    }
}
