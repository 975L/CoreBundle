<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\PlayRepository;
use Doctrine\ORM\Mapping as ORM;

// One time a visitor listened to, or watched, something to its end - a story, a film. Named rather than related and held under the same key as a Favorite ("u42" or the token a device keeps, see FavoriteService::resolveHolder()), so what was played anonymously follows its holder to the account they sign in to. One row per play and no unique constraint: how many times is the whole point, and when is what a reward for listening will be counted from
#[ORM\Entity(repositoryClass: PlayRepository::class)]
#[ORM\Table(name: 'site_play')]
#[ORM\Index(name: 'idx_play_holder', columns: ['holder'])]
#[ORM\Index(name: 'idx_play_owner', columns: ['owner_type', 'owner_id'])]
class Play
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $ownerType;

    #[ORM\Column]
    private int $ownerId;

    #[ORM\Column(length: 40)]
    private string $holder;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerType(): string
    {
        return $this->ownerType;
    }

    public function setOwnerType(string $ownerType): static
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): static
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getHolder(): string
    {
        return $this->holder;
    }

    public function setHolder(string $holder): static
    {
        $this->holder = $holder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
