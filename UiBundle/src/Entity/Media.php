<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Validator\FixedIconFormat;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'site_media')]
#[Vich\Uploadable]
#[FixedIconFormat]
class Media implements VichImageResizableInterface, VichMediaNamableInterface
{
    private const int IMAGE_WIDTH = 800;

    // Where a media reserved to members is moved, relative to the project root - the directory ShopBundle's paid downloads already live in, which the backup archives next to public/
    public const string MEMBERS_ONLY_DIRECTORY = 'private';

    // Site-wide graphics, not attached to a Block - fixed filename at the root of public/ (see getVichMediaPath), one row per role enforced at the application level (see isSingletonRole)
    public const ROLE_FAVICON = 'favicon';
    public const ROLE_APPLE_TOUCH_ICON = 'apple-touch-icon';
    public const ROLE_OG_IMAGE = 'og-image';
    public const ROLE_LOGO = 'logo';
    // The same logo drawn for a dark page, uploaded next to the one above the way the two watermarks are: a logo whose lettering is black disappears into a dark navbar, and no filter lightens it without flattening the colours around that lettering. Optional - a site whose logo reads on both grounds uploads none, and the one above is then used in both modes (see SiteBundle's Navbar)
    public const ROLE_LOGO_ON_DARK = 'logo-on-dark';

    // The two signatures stamped into a corner of an uploaded photo, named after the background they are meant to be read against: a dark logo for a light corner, a light one for a dark corner. Which of the two a given photo gets is decided on that corner's own luminance (see ImageWatermarker), so a site wanting a watermark at all uploads both - one alone is used for every photo, readable or not
    public const ROLE_WATERMARK_ON_LIGHT = 'watermark-on-light';
    public const ROLE_WATERMARK_ON_DARK = 'watermark-on-dark';

    // Site-wide but repeatable role: several rows share it (e.g. a pool of images picked at random), each gets its own filename
    public const ROLE_ERROR_IMAGE = 'error-image';

    private const array SINGLETON_ROLES = [
        self::ROLE_FAVICON,
        self::ROLE_APPLE_TOUCH_ICON,
        self::ROLE_OG_IMAGE,
        self::ROLE_LOGO,
        self::ROLE_LOGO_ON_DARK,
        self::ROLE_WATERMARK_ON_LIGHT,
        self::ROLE_WATERMARK_ON_DARK,
    ];

    // Roles needing a fixed target size/format regardless of the uploaded file (see UiMediaNamer/VichImageResizeListener). Favicon stays .ico (48x48 is the historical browser/OS expectation), apple-touch-icon stays .png (iOS ignores other formats)
    private const array FIXED_ICON_SPECS = [
        self::ROLE_FAVICON => ['width' => 48, 'height' => 48, 'format' => 'ico'],
        self::ROLE_APPLE_TOUCH_ICON => ['width' => 114, 'height' => 114, 'format' => 'png'],
    ];

    // Roles resized to a max width (aspect ratio kept, unlike FIXED_ICON_SPECS) instead of the default IMAGE_WIDTH
    private const array MAX_WIDTHS = [
        self::ROLE_LOGO => 600,
        self::ROLE_LOGO_ON_DARK => 600,
    ];

    // Block kinds needing a wider stored image than IMAGE_WIDTH (block medias all share role=null, so MAX_WIDTHS above can't key on them). Hero crops tightly via CSS object-fit:cover into a 4/3.2 box and can display up to 520px CSS-wide - on a retina/2x display that needs ~1040 native pixels, and the default 800 falls short, visibly pixelating once cover crops further into the image
    private const array BLOCK_KIND_MAX_WIDTHS = [
        'hero' => 1200,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Block::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Block $block = null;

    // Block medias all share role=null. Singleton roles (favicon, logo...) are kept to one row each, enforced at the application level (see SiteGraphicCrudController) since repeatable roles (error-image) need several rows sharing the same role - no DB-level unique constraint here
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $role = null;

    #[Vich\UploadableField(
        mapping: 'block_media',
        fileNameProperty: 'filename',
        size: 'size',
        mimeType: 'mimeType'
    )]
    private ?File $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private int $position = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    // Admin-typed short name (e.g. "Rapport annuel"), slugified by UiMediaNamer into the stored/physical filename (e.g. "rapport-annuel-xxx.pdf") instead of the default "block-{kind}-{id}" - distinct from $label, which is display/caption text and isn't filesystem-safe (accents, punctuation, could contain markup)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $width = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $height = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cssClasses = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $above = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $credits = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $rightsReserved = false;

    // Reserved to signed-in visitors: the file leaves public/ for MEMBERS_ONLY_DIRECTORY and is only ever served by MediaController, behind MediaVoter
    #[ORM\Column(options: ['default' => false])]
    private bool $membersOnly = false;

    // Per-media outbound link/caption pair, exposed by MediaUploadType's "portfolio_grid" context (see PortfolioGridType) - a project card's title reuses the existing $label field instead
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // What this media says in the language being rendered, laid over the three texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a page rendered in English cannot write English over the text the media was written in (see MediaTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    // "SET NULL" and not the default: this only records who last uploaded the file, and a media outlives whoever put it there - left restricting, an account that ever dropped one file could no longer be deleted at all
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?UserInterface $user = null;

    // Transient, never persisted - set by SiteBundle's BlockDataImporter when a Sync import's archive already carries a pre-generated PDF thumbnail, so VichPdfThumbnailListener can copy it as-is instead of re-running Ghostscript, unavailable on some hosts
    private ?string $importedThumbnailPath = null;

    // Leaves "user" out of a serialized media, for the same reason as Block::__serialize(): the site graphics are cached whole (see MediaExtension::preloadSingletonRoles()), and so are the medias of a cached block, where serialize() would load the uploader's User row - or throw once that account was gone. It comes back null
    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        $data = (array) $this;
        unset($data["\0" . self::class . "\0user"]);

        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlock(): ?Block
    {
        return $this->block;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function setBlock(?Block $block): self
    {
        $this->block = $block;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
        if (null !== $file) {
            $this->updatedAt = new \DateTimeImmutable();
        }
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
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

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->translated['alt'] ?? $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->translated['label'] ?? $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function setWidth(?string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?string
    {
        return $this->height;
    }

    public function setHeight(?string $height): self
    {
        $this->height = $height;

        return $this;
    }

    // $width/$height are admin-typed display values ("50%", "100px", "auto" are all valid there), while HTML's width/height attributes only accept a bare pixel count and silently discard anything else - these two return the value only when it is one, so a template can tell "intrinsic dimensions known" from "some css length"
    public function getIntrinsicWidth(): ?int
    {
        return ctype_digit((string) $this->width) ? (int) $this->width : null;
    }

    public function getIntrinsicHeight(): ?int
    {
        return ctype_digit((string) $this->height) ? (int) $this->height : null;
    }

    public function getCssClasses(): array
    {
        return $this->cssClasses ?? [];
    }

    public function setCssClasses(?array $cssClasses): self
    {
        $this->cssClasses = $cssClasses;

        return $this;
    }

    public function isAbove(): bool
    {
        return $this->above;
    }

    public function setAbove(?bool $above): self
    {
        $this->above = $above ?? false;

        return $this;
    }

    public function getCredits(): ?string
    {
        return $this->credits;
    }

    public function setCredits(?string $credits): self
    {
        $this->credits = $credits;

        return $this;
    }

    public function isRightsReserved(): bool
    {
        return $this->rightsReserved;
    }

    public function setRightsReserved(?bool $rightsReserved): self
    {
        $this->rightsReserved = $rightsReserved ?? false;

        return $this;
    }

    // A PDF only: an image carries -thumb/-highres siblings a move would leave behind in public/, so the flag is ignored on any other file
    public function isMembersOnly(): bool
    {
        return $this->membersOnly && $this->isPdf();
    }

    public function setMembersOnly(?bool $membersOnly): self
    {
        $this->membersOnly = $membersOnly ?? false;

        return $this;
    }

    // Read off the stored name, as every other PDF lookup of this bundle is (see MediaRepository::findPdfs())
    public function isPdf(): bool
    {
        return str_ends_with(strtolower((string) $this->filename), '.pdf');
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

    // Lays what a language says over the texts this media was written with, for the render being built and no longer than that - only MediaTranslator calls it, and only on the front, a form screen having to go on reading the row
    /** @param array<string, string|null> $values field => value */
    public function setTranslated(array $values): void
    {
        $this->translated = $values;
    }

    // The text the media itself carries, whatever language is being rendered - what a language screen offers as the thing to translate, and what tells an untouched field from a written one (see MediaTranslator)
    public function getUntranslated(string $field): ?string
    {
        return match ($field) {
            'alt' => $this->alt,
            'label' => $this->label,
            'description' => $this->description,
            default => null,
        };
    }

    public function getDescription(): ?string
    {
        return $this->translated['description'] ?? $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getImportedThumbnailPath(): ?string
    {
        return $this->importedThumbnailPath;
    }

    public function setImportedThumbnailPath(?string $importedThumbnailPath): self
    {
        $this->importedThumbnailPath = $importedThumbnailPath;

        return $this;
    }

    public function getImageWidth(): int
    {
        // The site-wide default og-image - kept lighter than the 1200px social platforms often suggest, well above their minimum (~200px)
        if ($this->isOgImage()) {
            return 600;
        }

        if (null !== $this->role) {
            return self::MAX_WIDTHS[$this->role] ?? self::IMAGE_WIDTH;
        }

        return self::BLOCK_KIND_MAX_WIDTHS[$this->block?->getKind() ?? ''] ?? self::IMAGE_WIDTH;
    }

    // Non-null only for roles needing a fixed target size/format (see FIXED_ICON_SPECS)
    public function getFixedIconSpec(): ?array
    {
        return null !== $this->role ? (self::FIXED_ICON_SPECS[$this->role] ?? null) : null;
    }

    // True only for the site-wide default og-image (role=og-image). A Page's own og-image override and a library Media added via MediaCrudController's New action (see MediaCrudController) share the exact same role=null/block=null state and are indistinguishable here - UiBundle has no visibility into a Page's own fields (see MediaUsageProviderInterface) - so neither gets the og-image-specific width override, only the true site-wide singleton does
    public function isOgImage(): bool
    {
        return self::ROLE_OG_IMAGE === $this->role;
    }

    // Singleton roles (favicon, logo...) only, repeatable roles (error-image) share filename naming with block medias
    public function isSingletonRole(): bool
    {
        return in_array($this->role, self::SINGLETON_ROLES, true);
    }

    // Public accessor for the fixed, small SINGLETON_ROLES list - lets MediaRepository::findBySingletonRoles() batch-fetch every site-wide singleton role (logo, favicon...) in one query instead of one query per role (see MediaExtension), without duplicating the list itself
    public static function getSingletonRoles(): array
    {
        return self::SINGLETON_ROLES;
    }

    // Same list read without a row in hand, for whoever needs a role's stored extension rather than an instance's - UiBackupPathProvider names the files to back up before any of them is loaded
    public static function getFixedIconSpecs(): array
    {
        return self::FIXED_ICON_SPECS;
    }

    public function getVichMediaPath(): string
    {
        // Singleton site-wide graphics live at the root of public/ under their own fixed name (see UiMediaNamer)
        if ($this->isSingletonRole()) {
            return $this->role;
        }

        // Repeatable site-wide role (e.g. error-image): several rows, each needs its own unique filename
        if (null !== $this->role) {
            return 'medias/site/' . $this->role;
        }

        // Not attached to a Block either (e.g. a Page's own og-image): still gets a unique, non-role name
        $block = $this->getBlock();
        if (null === $block) {
            return 'medias/site/media';
        }

        return 'medias/site/block-' . ($block->getKind() ?? 'unknown') . '-' . ($block->getId() ?? uniqid());
    }
}
