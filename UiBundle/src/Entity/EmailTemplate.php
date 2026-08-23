<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\EmailTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// A named, admin-composed email body: a sortable collection of EmailBlock rows rendered email-safe (table layout, inline CSS, no JS) by EmailTemplateRenderer - deliberately its own small system, not a reuse of c975L\UiBundle\Entity\Block (whose kinds are open-ended/DI-registered, unfit for a closed email-safe vocabulary) - see EmailBlock::TYPES and UiBundle Readme
#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Table(name: 'site_email_template')]
#[ORM\UniqueConstraint(name: 'UNIQ_email_template_name_locale', columns: ['name', 'locale'])]
#[UniqueEntity(fields: ['name', 'locale'], message: 'error.email_template_name_locale_taken')]
class EmailTemplate implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Looked up by callers (e.g. SendEmailFormAction's "emailTemplate" actionConfig key) - unlike Form::$name, purely an internal lookup key, never shown to a visitor. Unique with the locale and not on its own: one name is one email, written once per language the site answers in
    #[ORM\Column(length: 50)]
    private ?string $name = null;

    // The language this version is written in. A transactional e-mail is read by whoever the site was speaking to, not by whoever set the site up, so the name alone names an e-mail and the pair names the version of it to send (see EmailTemplateRepository::findForRendering, which falls back on the site's own language and then on any)
    #[ORM\Column(length: 5)]
    private ?string $locale = null;

    // A template seeded by its owning bundle/app (e.g. a future "form_submission" default) - see EmailTemplateCrudController, which locks "name" and deletion for such rows, same spirit as Form::$restricted
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

    // The data blocks (slots, fields tables) this template has already been offered, whether they are still in it or not - the difference between one that never received a block its bundle declares and one whose admin took that block out.
    //
    // Without it a backfill has no way to tell those two apart, and would put back on every deployment what somebody removed on purpose, which is worse than never offering it at all. Names and not positions: where a block sits is the admin's business, and they move theirs around
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $seededBlocks = [];

    // The documents this email carries, as a list of the kinds EmailAttachmentRegistry offers. Stored here and not decided in code: whether an order confirmation travels with the terms of sale is a shopkeeper's answer about their own shop, the same as every sentence of the body beside it
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $attachments = [];

    #[ORM\OneToMany(mappedBy: 'emailTemplate', targetEntity: EmailBlock::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return null === $this->locale || '' === $this->locale ? (string) $this->name : $this->name . ' (' . $this->locale . ')';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): self
    {
        $this->restricted = $restricted;

        return $this;
    }

    /** @return list<string> */
    public function getAttachments(): array
    {
        return array_values($this->attachments);
    }

    /** @param ?array<string> $attachments */
    public function setAttachments(?array $attachments): self
    {
        $this->attachments = array_values($attachments ?? []);

        return $this;
    }

    /** @return Collection<int, EmailBlock> */
    /**
     * @return list<string>
     */
    public function getSeededBlocks(): array
    {
        return $this->seededBlocks;
    }

    /**
     * @param list<string> $seededBlocks
     */
    public function setSeededBlocks(array $seededBlocks): self
    {
        $this->seededBlocks = array_values(array_unique($seededBlocks));

        return $this;
    }

    // Records that this template was offered that block once, so nothing offers it again
    public function markBlockSeeded(string $name): self
    {
        if (!in_array($name, $this->seededBlocks, true)) {
            $this->seededBlocks[] = $name;
        }

        return $this;
    }

    public function hasBlockBeenSeeded(string $name): bool
    {
        return in_array($name, $this->seededBlocks, true);
    }

    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function addBlock(EmailBlock $block): self
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
            $block->setEmailTemplate($this);
        }

        return $this;
    }

    public function removeBlock(EmailBlock $block): self
    {
        if ($this->blocks->removeElement($block)) {
            if ($block->getEmailTemplate() === $this) {
                $block->setEmailTemplate(null);
            }
        }

        return $this;
    }
}
