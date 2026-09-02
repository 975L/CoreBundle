<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\EmailBlockRepository;
use Doctrine\ORM\Mapping as ORM;

// One row of an EmailTemplate's body: a closed vocabulary sharing one flat set of columns, each meaningful only for the kinds noted below
#[ORM\Entity(repositoryClass: EmailBlockRepository::class)]
#[ORM\Table(name: 'site_email_block')]
class EmailBlock
{
    public const TYPE_HEADING = 'heading';
    public const TYPE_TEXT = 'text';
    public const TYPE_BUTTON = 'button';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DIVIDER = 'divider';
    public const TYPE_SPACER = 'spacer';
    public const TYPE_FIELDS_TABLE = 'fields_table';

    // Markup an admin wrote, rendered as-is rather than escaped like a text block: the only kind where a link, a bold word or a list survives the send. Placeholder values are still escaped on the way in (see EmailTemplateRenderer::contentFor())
    public const TYPE_HTML = 'html';

    // A fragment the sending code computes and hands over, not something an admin writes: an order's lines, its delivery address, its download links. The block says where it goes and under which name, the code says what it holds (see EmailTemplateRenderer and the "slots" variable)
    public const TYPE_SLOT = 'slot';

    public const TYPES = [
        self::TYPE_HEADING,
        self::TYPE_TEXT,
        self::TYPE_HTML,
        self::TYPE_BUTTON,
        self::TYPE_IMAGE,
        self::TYPE_DIVIDER,
        self::TYPE_SPACER,
        self::TYPE_FIELDS_TABLE,
        self::TYPE_SLOT,
    ];

    // The two kinds whose content the code supplies rather than the admin: an order's lines, a form's submitted fields. They are what makes an email carry what it is for, so a seeded template keeps them - they move, they are never deleted (see EmailTemplateCrudController and EmailBlockType)
    public const DATA_TYPES = [
        self::TYPE_FIELDS_TABLE,
        self::TYPE_SLOT,
    ];

    public const LEVEL_H1 = 'h1';
    public const LEVEL_H2 = 'h2';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EmailTemplate::class, inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EmailTemplate $emailTemplate = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // TYPE_HEADING's text
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $heading = null;

    // TYPE_HEADING's size - self::LEVEL_H1/LEVEL_H2
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $level = null;

    // TYPE_TEXT: plain text, not rich, so the email-safe HTML stays fully controlled server-side
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    // TYPE_BUTTON's label
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

    // TextType, not UrlType: may hold a "{{ variable }}" placeholder resolved at render time
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    // TYPE_IMAGE's alt text
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    // TYPE_SPACER's height, in pixels
    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    // Whether this block is one the code fills in, and so one a seeded template must keep
    public function isDataBlock(): bool
    {
        return in_array($this->type, self::DATA_TYPES, true);
    }

    public function getEmailTemplate(): ?EmailTemplate
    {
        return $this->emailTemplate;
    }

    public function setEmailTemplate(?EmailTemplate $emailTemplate): self
    {
        $this->emailTemplate = $emailTemplate;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function setHeading(?string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }
}
