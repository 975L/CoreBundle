<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Entity;

use c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Entity\Trait\NamedTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// One property per case of the entity rules, read by CodeRulesTestCaseTest
#[ORM\Entity]
class Item
{
    use NamedTrait;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $label = null;

    // Bad: no Length
    #[ORM\Column(length: 100)]
    private string $name = '';

    // Bad: a Length longer than the column
    #[ORM\Column(length: 10)]
    #[Assert\Length(max: 20)]
    private string $code = '';

    // A Length nested in a Sequentially counts
    #[ORM\Column(length: 50)]
    #[Assert\Sequentially([new Assert\NotBlank(), new Assert\Length(max: 50)])]
    private string $slug = '';

    #[ORM\Column(length: 20, enumType: ItemKind::class)]
    private ItemKind $kind = ItemKind::Simple;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $price = 0;

    // Bad: an amount without a bound
    #[ORM\Column]
    private int $stock = 0;

    // Not an amount
    #[ORM\Column]
    private int $position = 0;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getKind(): ItemKind
    {
        return $this->kind;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
