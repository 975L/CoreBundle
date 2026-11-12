<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Repository\FontRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// An admin-uploaded font file turned into a @font-face rule by FontCssListener; its own Vich field, not a Media, being no image to resize
#[ORM\Entity(repositoryClass: FontRepository::class)]
#[ORM\Table(name: 'site_font')]
#[Vich\Uploadable]
class Font implements VichMediaNamableInterface, \Stringable
{
    // Sentinel meaning "variable font": real weights start at 100, and the actual fvar axis is unreadable behind Brotli
    public const WEIGHT_VARIABLE = 0;

    // The font files a row may hold, whether uploaded one by one, in bulk or by an import
    public const array FILE_EXTENSIONS = ['ttf', 'woff', 'woff2'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Several rows may share one family name with a different weight or style
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => 400])]
    #[Assert\Range(min: 1, max: 1000)]
    private int $weight = 400;

    #[ORM\Column(length: 10, options: ['default' => 'normal'])]
    #[Assert\Length(max: 10)]
    private string $style = 'normal';

    #[Vich\UploadableField(
        mapping: 'site_font',
        fileNameProperty: 'filename',
        size: 'size',
        mimeType: 'mimeType'
    )]
    private ?File $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $filename = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $mimeType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): self
    {
        $this->weight = $weight ?? 400;

        return $this;
    }

    public function isVariable(): bool
    {
        return self::WEIGHT_VARIABLE === $this->weight;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function setStyle(?string $style): self
    {
        $this->style = $style ?? 'normal';

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    // A new file alone changes no mapped column, so Doctrine would skip the update and Vich never store it: clearing size, which Vich fills again on upload, makes the row dirty
    public function setFile(?File $file): self
    {
        $this->file = $file;
        if (null !== $file) {
            $this->size = null;
        }

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    // Format token expected by @font-face's src: url(...) format(...), derived from the uploaded file's own extension
    public function getFormat(): ?string
    {
        return match (strtolower(pathinfo($this->filename ?? '', PATHINFO_EXTENSION))) {
            'ttf' => 'truetype',
            'woff' => 'woff',
            'woff2' => 'woff2',
            default => null,
        };
    }

    // Unique non-role path; the id is still null on first upload, Vich running before the auto-increment
    public function getVichMediaPath(): string
    {
        return 'medias/fonts/font-' . ($this->id ?? uniqid());
    }

    public function __toString(): string
    {
        $weight = $this->isVariable() ? 'variable' : (string) $this->weight;

        return trim(($this->name ?? '') . ' ' . $weight . ' ' . $this->style);
    }
}
