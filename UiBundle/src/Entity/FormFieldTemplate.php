<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FormFieldTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// A reusable "already complete" FormField blueprint (e.g. "Email", "Consentement RGPD") picked from a select on a Form's own field collection (see FormCrudController/assets/js/form-field-template.js) instead of composing every property by hand each time - copied client-side into a fresh FormField row, never referenced afterward, so editing/deleting a template never touches Forms that already used it
#[ORM\Entity(repositoryClass: FormFieldTemplateRepository::class)]
#[ORM\Table(name: 'site_form_field_template')]
#[UniqueEntity('name')]
class FormFieldTemplate implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Shown as the select's own option label - purely an internal lookup key, never shown to a visitor, same spirit as Form::$name/EmailTemplate::$name
    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    // Copied into the new FormField's own "label"
    #[ORM\Column(length: 100)]
    private ?string $fieldLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeholder = null;

    #[ORM\Column(length: 20)]
    private string $type = FormField::TYPE_TEXT;

    #[ORM\Column]
    private bool $required = false;

    // A template seeded by its owning bundle/app (e.g. UiBundle's own "email"/"rgpd_consent" defaults) - see FormFieldTemplateCrudController, which locks "name" and deletion for such rows, same spirit as Form::$restricted/EmailTemplate::$restricted - unlike those, every other property here stays freely editable even when restricted, since that's the whole point of letting Laurent tweak a seeded template's wording
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

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

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    public function setFieldLabel(string $fieldLabel): static
    {
        $this->fieldLabel = $fieldLabel;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): static
    {
        $this->restricted = $restricted;

        return $this;
    }
}
