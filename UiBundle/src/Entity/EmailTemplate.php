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
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// A named, admin-composed email body: a sortable collection of EmailBlock rows rendered email-safe (table layout, inline CSS, no JS) by EmailTemplateRenderer - deliberately its own small system, not a reuse of c975L\UiBundle\Entity\Block (whose kinds are open-ended/DI-registered, unfit for a closed email-safe vocabulary) - see EmailBlock::TYPES and UiBundle Readme
#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Table(name: 'site_email_template')]
#[UniqueEntity('name')]
class EmailTemplate implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Looked up by callers (e.g. SendEmailFormAction's "emailTemplate" actionConfig key) - unlike Form::$name, purely an internal lookup key, never shown to a visitor
    #[ORM\Column(length: 50, unique: true)]
    private ?string $name = null;

    // A template seeded by its owning bundle/app (e.g. a future "form_submission" default) - see EmailTemplateCrudController, which locks "name" and deletion for such rows, same spirit as Form::$restricted
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

    #[ORM\OneToMany(mappedBy: 'emailTemplate', targetEntity: EmailBlock::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
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

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): self
    {
        $this->restricted = $restricted;

        return $this;
    }

    /** @return Collection<int, EmailBlock> */
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
