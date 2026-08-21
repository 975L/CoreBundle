<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\RatingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// One visitor's score on one thing - a book, a photo, an article. The thing is named rather than related (see $ownerType), so a bundle gets ratings without mapping anything on its own entity and without this bundle ever hearing about it
#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ORM\Table(name: 'site_rating')]
#[ORM\UniqueConstraint(name: 'uniq_rating_owner_voter', columns: ['owner_type', 'owner_id', 'voter'])]
#[ORM\Index(name: 'idx_rating_owner', columns: ['owner_type', 'owner_id'])]
class Rating implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The short stable string the owning bundle chose for its kind of thing ("book", "gallery_media"...), the same vocabulary BlockOwnerResolverInterface round-trips. A plain column rather than a relation: a rating is accumulated, not composed, and a join table per owner would make every bundle map a collection it never reads
    #[ORM\Column(length: 50)]
    private string $ownerType;

    #[ORM\Column]
    private int $ownerId;

    // Who voted, as one opaque key: "u42" for an authenticated visitor, a random token held by the browser otherwise (see RatingService::resolveVoter()). One column for both, so the unique constraint below is the only thing that has to enforce "one vote each"
    #[ORM\Column(length: 40)]
    private string $voter;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $value;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s#%d: %d', $this->ownerType, $this->ownerId, $this->value);
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

    public function getVoter(): string
    {
        return $this->voter;
    }

    public function setVoter(string $voter): self
    {
        $this->voter = $voter;

        return $this;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): self
    {
        $this->value = $value;

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
