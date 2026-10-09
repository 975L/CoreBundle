<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use c975L\UiBundle\Repository\SharedBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

// A named run of blocks written once and shown wherever a "shared_block" pointer names it - the call to action closing every page, a banner repeated on a dozen of them. An owner like a Page or a Menu, so editing, caching and drag & drop work on it with nothing of their own (see SharedBlockCrudController)
#[ORM\Entity(repositoryClass: SharedBlockRepository::class)]
#[ORM\Table(name: 'site_shared_block')]
#[UniqueEntity('slug')]
class SharedBlock implements HasBlocksInterface, \Stringable
{
    use HasBlocksTrait;

    // The kind of the block a page places to show one, which stores nothing but the slug below
    public const string POINTER_KIND = 'shared_block';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    // What a pointer stores, built once from the name and never changed after (see SharedBlockCrudController::persistEntity()): a renamed shared block keeps every page pointing at it
    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'site_shared_block_blocks')]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
