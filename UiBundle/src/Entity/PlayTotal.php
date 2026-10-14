<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\PlayTotalRepository;
use Doctrine\ORM\Mapping as ORM;

// How many times something was played to its end, by anyone: the site's own listeners and its apps', signed in or not. A count and nobody's, kept apart from Play - whose rows are one holder's and are handed over to an account when it connects, which would count the same listens twice
#[ORM\Entity(repositoryClass: PlayTotalRepository::class)]
#[ORM\Table(name: 'site_play_total')]
#[ORM\UniqueConstraint(name: 'uniq_play_total_owner', columns: ['owner_type', 'owner_id'])]
class PlayTotal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $ownerType;

    #[ORM\Column]
    private int $ownerId;

    #[ORM\Column]
    private int $plays = 0;

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

    public function getPlays(): int
    {
        return $this->plays;
    }

    public function setPlays(int $plays): static
    {
        $this->plays = $plays;

        return $this;
    }
}
