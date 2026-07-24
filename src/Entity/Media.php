<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use App\Entity\User;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'site_media')]
#[Vich\Uploadable]
class Media implements VichImageResizableInterface, VichMediaNamableInterface
{
    private const IMAGE_WIDTH = 800;

    // Site-wide graphics, not attached to a Block - fixed filename at the root of public/ (see getVichMediaPath), one row per role enforced at the application level (see isSingletonRole)
    public const ROLE_FAVICON = 'favicon';
    public const ROLE_APPLE_TOUCH_ICON = 'apple-touch-icon';
    public const ROLE_OG_IMAGE = 'og-image';
    public const ROLE_LOGO = 'logo';

    // Site-wide but repeatable role: several rows share it (e.g. a pool of images picked at random), each gets its own filename
    public const ROLE_ERROR_IMAGE = 'error-image';

    private const SINGLETON_ROLES = [
        self::ROLE_FAVICON,
        self::ROLE_APPLE_TOUCH_ICON,
        self::ROLE_OG_IMAGE,
        self::ROLE_LOGO,
    ];

    // Roles needing a fixed target size/format regardless of the uploaded file (see UiMediaNamer/VichImageResizeListener). Favicon stays .ico (48x48 is the historical browser/OS expectation), apple-touch-icon stays .png (iOS ignores other formats)
    private const FIXED_ICON_SPECS = [
        self::ROLE_FAVICON => ['width' => 48, 'height' => 48, 'format' => 'ico'],
        self::ROLE_APPLE_TOUCH_ICON => ['width' => 114, 'height' => 114, 'format' => 'png'],
    ];

    // FIXED_ICON_SPECS roles go through GD (see VichImageResizeListener::processFixedIcon), which cannot rasterize SVG - an SVG upload there silently produces a broken icon (readable by lenient tools like GIMP/browsers, but rejected by gdk-pixbuf, breaking OS-level thumbnails e.g. in Nemo/Nautilus)
    private const FIXED_ICON_ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    // Roles resized to a max width (aspect ratio kept, unlike FIXED_ICON_SPECS) instead of the default IMAGE_WIDTH
    private const MAX_WIDTHS = [
        self::ROLE_LOGO => 600,
    ];

    // Block kinds needing a wider stored image than IMAGE_WIDTH (block medias all share role=null, so MAX_WIDTHS above can't key on them). Hero crops tightly via CSS object-fit:cover into a 4/3.2 box and can display up to 520px CSS-wide - on a retina/2x display that needs ~1040 native pixels, and the default 800 falls short, visibly pixelating once cover crops further into the image
    private const BLOCK_KIND_MAX_WIDTHS = [
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

    // Per-media outbound link/caption pair, exposed by MediaUploadType's "portfolio_grid" context (see PortfolioGridType) - a project card's title reuses the existing $label field instead
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
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
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    // Rejects SVG (and any other non-raster format) for FIXED_ICON_SPECS roles - see FIXED_ICON_ALLOWED_MIME_TYPES
    #[Assert\Callback]
    public function validateFixedIconMimeType(ExecutionContextInterface $context): void
    {
        if (null === $this->getFixedIconSpec() || null === $this->file) {
            return;
        }

        if (!in_array($this->file->getMimeType(), self::FIXED_ICON_ALLOWED_MIME_TYPES, true)) {
            $context->buildViolation('label.fixed_icon_invalid_format')
                ->atPath('file')
                ->setTranslationDomain('ui')
                ->addViolation();
        }
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
