<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\PlayController;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\FavoriteService;
use c975L\UiBundle\Service\PlayService;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// A page of the site counts what it played to its end, under its holder when there is one, and only for its own pages and things it can show
class PlayControllerTest extends TestCase
{
    /** @var list<string> the plays written down, with their holder */
    private array $plays = [];

    private function createController(?string $holder = null, bool $known = true, bool $accepted = true): PlayController
    {
        $playService = $this->createStub(PlayService::class);
        $playService->method('record')->willReturnCallback(function (string $ownerType, int $ownerId, ?string $holder): array {
            $this->plays[] = $ownerType . ':' . $ownerId . ' ' . ($holder ?? 'nobody');

            return [];
        });

        $favoriteService = $this->createStub(FavoriteService::class);
        $favoriteService->method('resolveHolder')->willReturn($holder);

        $registry = $this->createStub(FavoriteItemRegistry::class);
        $registry->method('resolve')->willReturn($known ? [['ownerType' => 'book', 'ownerId' => 12]] : []);

        $guard = $this->createStub(RateLimiterGuard::class);
        $guard->method('isAcceptedForIp')->willReturn($accepted);

        $controller = new PlayController($playService, $favoriteService, $registry, $guard);
        $controller->setContainer(new Container());

        return $controller;
    }

    private function request(string $origin = 'https://example.com'): Request
    {
        return Request::create('https://example.com/play/book/12', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $origin, 'REMOTE_ADDR' => '203.0.113.7'], content: '{}');
    }

    // A listener nobody knows is counted all the same
    public function testAnAnonymousPlayIsCounted(): void
    {
        $this->assertSame(204, $this->createController()->play('book', 12, $this->request())->getStatusCode());
        $this->assertSame(['book:12 nobody'], $this->plays);
    }

    // A member's play is theirs too
    public function testAMembersPlayIsHeldUnderTheirAccount(): void
    {
        $this->createController(holder: 'u42')->play('book', 12, $this->request());

        $this->assertSame(['book:12 u42'], $this->plays);
    }

    // Another site's page counts nothing here
    public function testAnotherOriginIsTurnedDown(): void
    {
        $this->assertSame(403, $this->createController()->play('book', 12, $this->request('https://elsewhere.example'))->getStatusCode());
        $this->assertSame([], $this->plays);
    }

    // The limit the wishlist answers to
    public function testTooManyCallsAreTurnedDown(): void
    {
        $this->assertSame(429, $this->createController(accepted: false)->play('book', 12, $this->request())->getStatusCode());
    }

    // Nothing is counted for a thing nobody on the site can show
    public function testAnUnknownThingIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(known: false)->play('book', 999, $this->request());
    }
}
