<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FormRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// Shared, generic "form definition" owning a sortable collection of FormField rows, so several bundles (ContactFormBundle today, a future form-builder tomorrow) can each manage their own named row (e.g. name="contact") in one table instead of each keeping a private fields table - see UiBundle Readme
#[ORM\Entity(repositoryClass: FormRepository::class)]
#[ORM\Table(name: 'site_form')]
#[UniqueEntity('name')]
class Form
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $name = null;

    // Key resolved via c975L\UiBundle\Registry\FormActionRegistry to process a submission (e.g. "send_email") - nullable, a Form with no action set simply can't be submitted yet
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $action = null;

    // Free-shape config consumed by whichever FormActionInterface "action" points to (e.g. send_email reads "to"/"from"/"subject"/"template"...) - same principle as Block::$data, interpreted differently per action, not by Form itself
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $actionConfig = null;

    // A Form seeded by its owning bundle (e.g. ContactFormBundle's "contact") - see FormCrudController, which disables the "name" field for such rows: still fully editable otherwise, just never renamed/duplicated into a conflicting identity
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

    // Lets an admin pause a Form without unpublishing its Page or clearing "action" - checked by FormController before building/submitting, see FormDisabled.html.twig
    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\OneToMany(mappedBy: 'form', targetEntity: FormField::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $fields;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getActionConfig(): ?array
    {
        return $this->actionConfig;
    }

    public function setActionConfig(?array $actionConfig): self
    {
        $this->actionConfig = $actionConfig;

        return $this;
    }

    // Virtual, not persisted - lets FormCrudController edit $actionConfig as raw JSON text instead of needing a dynamic per-action sub-form
    public function getActionConfigJson(): ?string
    {
        return null === $this->actionConfig ? null : json_encode($this->actionConfig, JSON_PRETTY_PRINT);
    }

    public function setActionConfigJson(?string $actionConfigJson): self
    {
        if (null === $actionConfigJson || '' === trim($actionConfigJson)) {
            $this->actionConfig = null;

            return $this;
        }

        $decoded = json_decode($actionConfigJson, true);
        $this->actionConfig = is_array($decoded) ? $decoded : null;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /** @return Collection<int, FormField> */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(FormField $field): self
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setForm($this);
        }

        return $this;
    }

    public function removeField(FormField $field): self
    {
        if ($this->fields->removeElement($field)) {
            if ($field->getForm() === $this) {
                $field->setForm(null);
            }
        }

        return $this;
    }
}
