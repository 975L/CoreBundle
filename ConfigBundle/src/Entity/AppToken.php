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
use c975L\ConfigBundle\Repository\AppTokenRepository;
use Doctrine\ORM\Mapping as ORM;

// What lets a mobile app act as a member without ever holding their password: a one-time code first, handed to the app through the browser the member signed in with, then the token the app trades it for (see AppTokenManager). Neither is stored, only their sha256: a copy of the table opens no account
#[ORM\Entity(repositoryClass: AppTokenRepository::class)]
#[ORM\Table(name: 'site_app_token')]
class AppToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // "CASCADE": a token is nothing without its account. An account anonymized rather than deleted loses its tokens through AppTokenSubscriber
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserInterface $user = null;

    // Null until the code is traded
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $tokenHash = null;

    // Null once traded: a code works once
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $codeHash = null;

    // What the app proves it knows the secret of when trading the code (PKCE, RFC 7636): the base64url sha256 of a verifier that never left the phone
    #[ORM\Column(length: 43, nullable: true)]
    private ?string $challenge = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $codeExpiration = null;

    // The device as the app names it, so a member can tell their tokens apart
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

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

    public function setUser(UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(?string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getCodeHash(): ?string
    {
        return $this->codeHash;
    }

    public function setCodeHash(?string $codeHash): static
    {
        $this->codeHash = $codeHash;

        return $this;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }

    public function setChallenge(?string $challenge): static
    {
        $this->challenge = $challenge;

        return $this;
    }

    public function getCodeExpiration(): ?\DateTimeImmutable
    {
        return $this->codeExpiration;
    }

    public function setCodeExpiration(?\DateTimeImmutable $codeExpiration): static
    {
        $this->codeExpiration = $codeExpiration;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
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
}
