<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Entity;

use c975L\ConfigBundle\Repository\NotFoundRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// A 404 someone was actually sent to, one row per path. Only requests carrying a Referer are recorded (see NotFoundSubscriber): a scanner walking "/wp-admin" sends none, so what lands here is a link that exists somewhere and no longer answers - either on the site itself, which is the case worth an alert, or on another one pointing at it
#[ORM\Entity(repositoryClass: NotFoundRepository::class)]
#[ORM\Table(name: NotFound::TABLE)]
#[ORM\Index(name: 'idx_not_found_last_seen', columns: ['last_seen'])]
class NotFound implements \Stringable
{
    // Named here rather than in the repository too: the rows are written in plain SQL (see NotFoundRepository::record()), which is the one place a table name would otherwise be repeated and left behind by a rename
    public const string TABLE = 'site_not_found';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The path alone, never the query string: "/histoire/abc?utm_source=x" and the same url shared without its tracking are one broken link, and one row to fix. Unique, so the table grows with the number of distinct dead urls rather than with the traffic hitting them
    #[ORM\Column(length: 255, unique: true)]
    private ?string $path = null;

    // The last referer seen, not the first: a link fixed on one page and still live on another has the page still carrying it as the one worth opening
    #[ORM\Column(length: 255)]
    private ?string $referer = null;

    // Whether that last referer is a page of this very site - what tells a broken link of our own from a stale link someone else publishes. The pair is one fact, so both columns describe the same last referer rather than each keeping its own history
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $internal = false;

    #[ORM\Column(options: ['default' => 1])]
    private int $hits = 1;

    #[ORM\Column(name: 'first_seen', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $firstSeen = null;

    #[ORM\Column(name: 'last_seen', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastSeen = null;

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

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(string $referer): self
    {
        $this->referer = $referer;

        return $this;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function setInternal(bool $internal): self
    {
        $this->internal = $internal;

        return $this;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function setHits(int $hits): self
    {
        $this->hits = $hits;

        return $this;
    }

    public function getFirstSeen(): ?\DateTimeInterface
    {
        return $this->firstSeen;
    }

    public function setFirstSeen(\DateTimeInterface $firstSeen): self
    {
        $this->firstSeen = $firstSeen;

        return $this;
    }

    public function getLastSeen(): ?\DateTimeInterface
    {
        return $this->lastSeen;
    }

    public function setLastSeen(\DateTimeInterface $lastSeen): self
    {
        $this->lastSeen = $lastSeen;

        return $this;
    }
}
