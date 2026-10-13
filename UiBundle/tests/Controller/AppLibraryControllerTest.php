<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\AppLibraryController;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\FavoriteService;
use c975L\UiBundle\Service\PlayService;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

// The app's favorites and plays are held under the device's token or the account, an order sent twice changes nothing, and what the device held follows it to the account
class AppLibraryControllerTest extends TestCase
{
    private const string TOKEN = '0123456789abcdef0123456789abcdef';

    /** @var list<string> the calls that changed something, in order */
    private array $calls = [];

    /** @param list<string> $keys what the holder already put aside */
    private function createController(?string $holder = self::TOKEN, array $keys = [], bool $known = true, bool $accepted = true): AppLibraryController
    {
        $favoriteService = $this->createStub(FavoriteService::class);
        $favoriteService->method('resolveHolder')->willReturn($holder);
        $favoriteService->method('keys')->willReturn($keys);
        $favoriteService->method('toggle')->willReturnCallback(function (string $ownerType, int $ownerId): array {
            $this->calls[] = 'toggle ' . $ownerType . ':' . $ownerId;

            return ['favorited' => true, 'count' => 1];
        });

        $playService = $this->createStub(PlayService::class);
        $playService->method('counts')->willReturn(['book:12' => 3]);
        $playService->method('record')->willReturnCallback(function (string $ownerType, int $ownerId): array {
            $this->calls[] = 'play ' . $ownerType . ':' . $ownerId;

            return [];
        });
        $playService->method('merge')->willReturnCallback(function (string $token, string $holder): void {
            $this->calls[] = 'merge ' . $token . ' > ' . $holder;
        });

        $registry = $this->createStub(FavoriteItemRegistry::class);
        $registry->method('resolve')->willReturn($known ? [['ownerType' => 'book', 'ownerId' => 12]] : []);

        $guard = $this->createStub(RateLimiterGuard::class);
        $guard->method('isAcceptedForIp')->willReturn($accepted);

        $controller = new AppLibraryController($favoriteService, $playService, $registry, $guard);

        $container = new Container();
        $container->set('security.token_storage', new TokenStorage());
        $controller->setContainer($container);

        return $controller;
    }

    /** @param array<string, mixed> $body */
    private function request(array $body = ['token' => self::TOKEN]): Request
    {
        return Request::create('/app/api/library', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: json_encode($body));
    }

    /** @return array<string, mixed> */
    private function data(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    // What the app shows on a new phone: the favorites and how many times each thing was played
    public function testTheLibraryHoldsTheFavoritesAndThePlays(): void
    {
        $response = $this->createController(keys: ['book:12'])->library($this->request());

        $this->assertSame(['favorites' => ['book:12'], 'plays' => ['book:12' => 3], 'connected' => false], $this->data($response));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    // Neither an account nor a token the device made up: there is no list to answer with
    public function testACallerWithNoKeyIsABadRequest(): void
    {
        $this->assertSame(400, $this->createController(holder: null)->library($this->request([]))->getStatusCode());
    }

    // The limit the site's own wishlist routes answer to
    public function testTooManyCallsAreTurnedDown(): void
    {
        $this->assertSame(429, $this->createController(accepted: false)->library($this->request())->getStatusCode());
    }

    // An order, not a toggle: asking twice to put a thing aside leaves it aside
    public function testPuttingAsideWhatAlreadyIsChangesNothing(): void
    {
        $this->createController(keys: ['book:12'])->favorite('book', 12, $this->request(['token' => self::TOKEN, 'favorited' => true]));

        $this->assertSame([], $this->calls);
    }

    // The two orders that do change something
    public function testAThingIsPutAsideAndTakenBackOut(): void
    {
        $this->createController()->favorite('book', 12, $this->request(['token' => self::TOKEN, 'favorited' => true]));
        $this->createController(keys: ['book:12'])->favorite('book', 12, $this->request(['token' => self::TOKEN, 'favorited' => false]));

        $this->assertSame(['toggle book:12', 'toggle book:12'], $this->calls);
    }

    // One row per play
    public function testAPlayIsWrittenDown(): void
    {
        $response = $this->createController()->play('book', 12, $this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['play book:12'], $this->calls);
    }

    // Nothing is counted for a thing nobody on the site can show
    public function testAnUnknownThingIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(known: false)->play('book', 999, $this->request());
    }

    // Connected, what the device played is handed over to the account
    public function testWhatTheDevicePlayedFollowsItToTheAccount(): void
    {
        $this->createController(holder: 'u42')->library($this->request());

        $this->assertSame(['merge ' . self::TOKEN . ' > u42'], $this->calls);
    }

    // A token of no known shape names no rows to move
    public function testAMalformedTokenMovesNothing(): void
    {
        $this->createController(holder: 'u42')->library($this->request(['token' => 'u1']));

        $this->assertSame([], $this->calls);
    }
}
