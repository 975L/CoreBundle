<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FormFieldRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: FormFieldRepository::class)]
#[ORM\Table(name: 'site_form_field')]
#[ORM\UniqueConstraint(name: 'form_field_unique', columns: ['form_id', 'name'])]
#[UniqueEntity(fields: ['form', 'name'])]
class FormField implements \Stringable
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL = 'email';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_PASSWORD = 'password';
    public const TYPE_PASSWORD_REPEATED = 'password_repeated';
    public const TYPE_URL = 'url';
    public const TYPE_TEL = 'tel';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_EMAIL,
        self::TYPE_CHECKBOX,
        self::TYPE_PASSWORD,
        self::TYPE_PASSWORD_REPEATED,
        self::TYPE_URL,
        self::TYPE_TEL,
        self::TYPE_NUMBER,
        self::TYPE_DATE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Form::class, inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Form $form = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    // Derived from "label", scoped unique within the owning Form - see c975L\UiBundle\Service\FormFieldNamer
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeholder = null;

    // Optional link shown next to the label (e.g. a checkbox field's "J'accepte les [CGU]" pointing at the real terms-of-use page) - see FormSubmissionType, which appends it to the label as a real <a> when set
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column]
    private bool $required = false;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // A field seeded by its owning bundle as part of a form's core identity (e.g. register's "email"/"plainPassword") - see FormFieldType, which disables the "type" field and the delete button for such rows: reorderable/relabellable, never removable or reclassifiable
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

    public function __toString(): string
    {
        return (string) $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position ?? 0;

        return $this;
    }
}
