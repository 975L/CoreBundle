<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

// One thing one visitor put aside - a product, a book, a photo. Named rather than related, exactly like Rating: a bundle gets a wishlist without mapping anything on its own entity, and this bundle never hears about what it holds (see FavoriteItemProviderInterface, which is what turns a row back into something a page can draw)
#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
#[ORM\Table(name: 'site_favorite')]
#[ORM\UniqueConstraint(name: 'uniq_favorite_owner_holder', columns: ['owner_type', 'owner_id', 'holder'])]
#[ORM\Index(name: 'idx_favorite_holder', columns: ['holder'])]
class Favorite implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The same vocabulary Rating stores and BlockOwnerResolverInterface round-trips, so a bundle naming its products "shop_product" once is named that everywhere
    #[ORM\Column(length: 50)]
    private string $ownerType;

    #[ORM\Column]
    private int $ownerId;

    // Whose list this is, as one opaque key: "u42" for an authenticated visitor, a random token held by their browser otherwise (see FavoriteService::resolveHolder()). One column for both, which is what lets a list built anonymously be handed over to an account without moving it anywhere (see FavoriteService::merge())
    #[ORM\Column(length: 40)]
    private string $holder;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s#%d', $this->ownerType, $this->ownerId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerType(): string
    {
        return $this->ownerType;
    }

    public function setOwnerType(string $ownerType): self
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getHolder(): string
    {
        return $this->holder;
    }

    public function setHolder(string $holder): self
    {
        $this->holder = $holder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
