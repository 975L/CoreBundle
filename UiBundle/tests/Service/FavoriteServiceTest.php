<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Repository\FavoriteRepository;
use c975L\UiBundle\Service\FavoriteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// Whose list a row belongs to, and what happens to a list built anonymously once its visitor signs in
class FavoriteServiceTest extends TestCase
{
    private const string TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testAnAuthenticatedVisitorHoldsTheirListOnTheirAccount(): void
    {
        $this->assertSame('u42', $this->service(userId: 42)->resolveHolder(self::TOKEN));
    }

    // The account wins over the browser, which is the whole of how a list follows someone onto another device
    public function testTheAccountWinsOverTheBrowserToken(): void
    {
        $this->assertNotSame(self::TOKEN, $this->service(userId: 42)->resolveHolder(self::TOKEN));
    }

    public function testAnAnonymousVisitorHoldsTheirListOnTheirOwnToken(): void
    {
        $this->assertSame(self::TOKEN, $this->service()->resolveHolder(self::TOKEN));
    }

    // Refused rather than filed under a key the server invented, which would give every request a list of its own
    public function testAnAnonymousVisitorWithoutAUsableTokenHoldsNothing(): void
    {
        $service = $this->service();

        $this->assertNull($service->resolveHolder(null));
        $this->assertNull($service->resolveHolder('not-a-token'));
        $this->assertNull($service->resolveHolder(strtoupper(self::TOKEN)));
    }

    public function testSigningInHandsTheBrowsersListOverToTheAccount(): void
    {
        $repository = $this->createMock(FavoriteRepository::class);
        $repository->expects($this->once())->method('moveHolder')->with(self::TOKEN, 'u42');

        $this->service(userId: 42, repository: $repository)->merge(self::TOKEN);
    }

    // Nothing to hand over: an anonymous visitor is already holding their list under the only key they have
    public function testAnAnonymousVisitorMergesNothing(): void
    {
        $repository = $this->createMock(FavoriteRepository::class);
        $repository->expects($this->never())->method('moveHolder');

        $this->service(repository: $repository)->merge(self::TOKEN);
    }

    // A signed-in visitor whose browser holds no token has nothing to bring, and a forged one must not move anybody's list
    public function testAMergeWithoutAUsableTokenMovesNothing(): void
    {
        $repository = $this->createMock(FavoriteRepository::class);
        $repository->expects($this->never())->method('moveHolder');

        $service = $this->service(userId: 42, repository: $repository);
        $service->merge(null);
        $service->merge('not-a-token');
    }

    // The keys every heart of the site repaints itself from
    public function testTheListIsAlsoPublishedAsTheKeysTheButtonsPaintThemselvesFrom(): void
    {
        $repository = $this->createStub(FavoriteRepository::class);
        $repository->method('findIdsByHolder')->willReturn(['shop_product' => [39, 14], 'book' => [3]]);

        $this->assertSame(['shop_product:39', 'shop_product:14', 'book:3'], $this->service(repository: $repository)->keys(self::TOKEN));
    }

    // The badge the toggle answers with is read against the drawer the next click opens: an item favorited then unpublished no longer resolves, and a count taken from the rows would keep announcing it
    public function testTheToggleCountsWhatTheDrawerShowsRatherThanTheRowsHeld(): void
    {
        $repository = $this->createStub(FavoriteRepository::class);
        $repository->method('findOneByHolder')->willReturn(null);
        $repository->method('findIdsByHolder')->willReturn(['shop_product' => [39, 14], 'book' => [3]]);
        $repository->method('countForHolder')->willReturn(3);

        $registry = $this->createStub(FavoriteItemRegistry::class);
        $registry->method('resolve')->willReturn([
            ['ownerType' => 'shop_product', 'ownerId' => 39, 'item' => new CollectionItem('A product')],
            ['ownerType' => 'book', 'ownerId' => 3, 'item' => new CollectionItem('A book')],
        ]);

        $result = $this->service(repository: $repository, registry: $registry)->toggle('shop_product', 14, self::TOKEN);

        $this->assertTrue($result['favorited']);
        $this->assertSame(2, $result['count']);
    }

    private function service(?int $userId = null, FavoriteRepository | MockObject | null $repository = null, ?FavoriteItemRegistry $registry = null): FavoriteService
    {
        $security = $this->createStub(Security::class);

        if (null !== $userId) {
            $user = $this->createStub(UserInterface::class);
            $user->method('getId')->willReturn($userId);
            $security->method('getUser')->willReturn($user);
        }

        return new FavoriteService(
            $repository ?? $this->createStub(FavoriteRepository::class),
            $registry ?? $this->createStub(FavoriteItemRegistry::class),
            $this->createStub(EntityManagerInterface::class),
            $security,
        );
    }
}
