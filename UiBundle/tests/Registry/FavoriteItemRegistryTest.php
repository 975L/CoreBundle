<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\FavoriteItemProviderInterface;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use PHPUnit\Framework\TestCase;

// What turns the rows of a wishlist back into something a page can draw - a Favorite storing a name and an id and nothing else
class FavoriteItemRegistryTest extends TestCase
{
    public function testTheEntriesAreDrawnInTheOrderTheyWereStored(): void
    {
        $registry = new FavoriteItemRegistry();
        // Answered in another order than asked, which is what a "WHERE id IN (...)" does
        $registry->addProvider($this->provider('book', [7 => 'Sept', 3 => 'Trois']));

        $resolved = $registry->resolve(['book' => [3, 7]]);

        $this->assertSame(['Trois', 'Sept'], array_map(static fn (array $entry): string => $entry['item']->title, $resolved));
    }

    // A bundle removed since, or one that never shipped a provider: its entries stay in the table and are simply not drawn
    public function testAKindNobodyResolvesIsLeftOut(): void
    {
        $registry = new FavoriteItemRegistry();
        $registry->addProvider($this->provider('book', [3 => 'Trois']));

        $this->assertSame([], $registry->resolve(['shop_product' => [12]]));
    }

    // A draft, something trashed, something withdrawn from sale: the provider answers nothing for it, and the list shows nothing rather than an empty card
    public function testAnIdTheProviderHasNothingToShowForIsLeftOut(): void
    {
        $registry = new FavoriteItemRegistry();
        $registry->addProvider($this->provider('book', [3 => 'Trois']));

        $this->assertCount(1, $registry->resolve(['book' => [3, 99]]));
    }

    public function testTheEntryCarriesWhatItWasStoredUnder(): void
    {
        $registry = new FavoriteItemRegistry();
        $registry->addProvider($this->provider('book', [3 => 'Trois']));

        $resolved = $registry->resolve(['book' => [3]]);

        $this->assertSame('book', $resolved[0]['ownerType']);
        $this->assertSame(3, $resolved[0]['ownerId']);
    }

    // Fails loudly rather than letting one provider silently win and the other become unreachable
    public function testTwoProvidersOnTheSameKindAreRefused(): void
    {
        $registry = new FavoriteItemRegistry();
        $registry->addProvider($this->provider('book', [3 => 'Trois']));
        $registry->addProvider($this->provider('book', [3 => 'Three']));

        $this->expectException(\LogicException::class);
        $registry->resolve(['book' => [3]]);
    }

    /**
     * @param array<int, string> $titles
     */
    private function provider(string $ownerType, array $titles): FavoriteItemProviderInterface
    {
        return new readonly class ($ownerType, $titles) implements FavoriteItemProviderInterface {
            public function __construct(
                private string $ownerType,
                private array $titles,
            ) {
            }

            public function supports(string $ownerType): bool
            {
                return $ownerType === $this->ownerType;
            }

            public function getItems(string $ownerType, array $ownerIds): array
            {
                $items = [];
                foreach ($this->titles as $id => $title) {
                    if (\in_array($id, $ownerIds, true)) {
                        $items[$id] = new CollectionItem($title);
                    }
                }

                return $items;
            }
        };
    }
}
