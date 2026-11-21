<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Repository\AgentTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// What lets an AI agent act on the site through its MCP endpoint with an account's rights, kept to what the token allows (writing or not, the bundles named); only its sha256 is stored, the token being shown once
#[ORM\Entity(repositoryClass: AgentTokenRepository::class)]
#[ORM\Table(name: 'site_agent_token')]
class AgentToken implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // A token is nothing without its account
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserInterface $user = null;

    // The agent as the account names it, to tell its tokens apart
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\Length(max: 64)]
    private string $tokenHash = '';

    // Whether the agent may change anything, reading only otherwise
    #[ORM\Column]
    private bool $writable = false;

    // Keys of the bundles whose tools the agent is given, every one when empty
    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $bundles = [];

    // Day past which the token opens nothing, never when null
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiration = null;

    #[ORM\Column]
    private \DateTimeImmutable $creation;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUse = null;

    public function __construct()
    {
        $this->creation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = mb_substr(trim((string) $name), 0, 100);

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function setWritable(bool $writable): static
    {
        $this->writable = $writable;

        return $this;
    }

    /** @return list<string> */
    public function getBundles(): array
    {
        return $this->bundles;
    }

    /** @param list<string> $bundles */
    public function setBundles(array $bundles): static
    {
        $this->bundles = array_values(array_unique(array_filter(array_map(trim(...), $bundles))));

        return $this;
    }

    // Whether the agent is given the tools of a bundle
    public function allows(string $bundle): bool
    {
        return [] === $this->bundles || \in_array($bundle, $this->bundles, true);
    }

    public function getExpiration(): ?\DateTimeImmutable
    {
        return $this->expiration;
    }

    public function setExpiration(?\DateTimeImmutable $expiration): static
    {
        $this->expiration = $expiration;

        return $this;
    }

    // Whether the token is past its day, the day itself still open (a DateField keeps its midnight)
    public function isExpired(\DateTimeImmutable $now): bool
    {
        return null !== $this->expiration && $this->expiration->setTime(0, 0) < $now->setTime(0, 0);
    }

    public function getCreation(): \DateTimeImmutable
    {
        return $this->creation;
    }

    public function getLastUse(): ?\DateTimeImmutable
    {
        return $this->lastUse;
    }

    public function setLastUse(?\DateTimeImmutable $lastUse): static
    {
        $this->lastUse = $lastUse;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
