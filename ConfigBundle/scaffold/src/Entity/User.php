<?php

namespace App\Entity;

use App\Repository\UserRepository;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\UiBundle\Validator\Constraints\DnsEmail;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

// c975L\ConfigBundle\Contract\UserInterface, not Symfony's own: it extends it and adds getId(), which is what the c975L bundles relate to (Page::$user, Block::$user...). ConfigBundle maps it onto this class through Doctrine's resolve_target_entities, so there is nothing to declare in config/. Implemented through InactivityAwareInterface, which extends it so c975l:config:users-cleanup can warn then anonymize the accounts left unused
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'text.email_taken')]
class User implements InactivityAwareInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'text.email_required')]
    #[Assert\Email(message: 'text.email_not_valid')]
    #[DnsEmail]
    private ?string $email = null;

    /** @var string[] */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private bool $isEnabled = false;

    #[ORM\Column]
    private ?\DateTime $creation = null;

    #[ORM\Column]
    private ?\DateTime $modification = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastLogin = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $inactivityNoticeSentAt = null;

    // The inactivity clock starts with the account, one never logged in to being as unused as one left for years
    public function __construct()
    {
        $this->lastLogin = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /** @param string[] $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getCreation(): ?\DateTime
    {
        return $this->creation;
    }

    public function setCreation(\DateTime $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?\DateTime
    {
        return $this->modification;
    }

    public function setModification(\DateTime $modification): static
    {
        $this->modification = $modification;

        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = \DateTime::createFromInterface($lastLogin);

        return $this;
    }

    public function getInactivityNoticeSentAt(): ?\DateTime
    {
        return $this->inactivityNoticeSentAt;
    }

    public function setInactivityNoticeSentAt(?\DateTimeInterface $inactivityNoticeSentAt): static
    {
        $this->inactivityNoticeSentAt = null !== $inactivityNoticeSentAt ? \DateTime::createFromInterface($inactivityNoticeSentAt) : null;

        return $this;
    }

    // Keeps the row for what refers to it (payments, credits...) and nothing that identifies a person, the random password locking it for good
    public function anonymize(): void
    {
        $this->email = 'anonymized-' . $this->id . '@' . self::ANONYMIZED_DOMAIN;
        $this->password = bin2hex(random_bytes(32));
        $this->roles = [];
        $this->isEnabled = false;
        $this->inactivityNoticeSentAt = null;
        $this->modification = new \DateTime();
    }
}
