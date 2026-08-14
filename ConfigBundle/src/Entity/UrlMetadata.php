<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Entity;

use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use c975L\UiBundle\Entity\Media;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

// What an url says of itself when no entity carries it - the title, the summary and the share image of a listing, an index, a filtered listing, a tool page. Everything a Page, a product or a photo already answers for itself is none of this table's business: a row here is only ever read when the template rendering the page set nothing, so an entity always speaks first (see UrlMetadataResolver and the two layouts).
// Keyed by path and not by route name, because one route serves many pages: "/caste/{caste}" is a single route and twelve listings that have twelve different things to say. Same reason - and same shape - as Redirect::$fromPath.
#[ORM\Entity(repositoryClass: UrlMetadataRepository::class)]
#[ORM\Table(name: 'site_url_metadata')]
#[UniqueEntity('path')]
class UrlMetadata implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $path = null;

    // Nullable like everything else here: a row is filled in as the site is written, and an url whose title is not written yet falls back on whatever the template and the layout already do. What it must not do is force an empty <title> on a page that had one
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    // The name Page's own column goes by, and the one both layouts read - so the same text feeds the meta description, og:description, and the url's line in llms.txt through its sitemap provider
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summarySocialNetwork = null;

    // Falls back to the site-wide default og-image when not set, exactly as a Page's does (see MediaExtension::getSiteMedia). cascade: remove - this Media belongs to this url alone and is not shared
    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $ogImage = null;

    public function __toString(): string
    {
        return $this->path ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    // Normalised on the way in, for the same reason Redirect::setFromPath() is: the stored path is compared to the one the request carries, and neither "animaux" nor "/animaux/" would ever equal "/animaux". Both ends are trimmed and the leading slash put back, so the site root stays "/" and every other path loses its trailing one - the form UrlMetadataResolver looks rows up by, and the one canonical_url() already declares
    public function setPath(string $path): self
    {
        $this->path = '/' . trim($path, '/');

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSummarySocialNetwork(): ?string
    {
        return $this->summarySocialNetwork;
    }

    public function setSummarySocialNetwork(?string $summarySocialNetwork): self
    {
        $this->summarySocialNetwork = $summarySocialNetwork;

        return $this;
    }

    public function getOgImage(): ?Media
    {
        return $this->ogImage;
    }

    public function setOgImage(?Media $ogImage): self
    {
        $this->ogImage = $ogImage;

        return $this;
    }
}
