<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\AiSearchChunkRepository;
use Doctrine\ORM\Mapping as ORM;

// A passage of a public page, as an anonymous visitor reads it - what the site search answers from. Rebuilt as a whole by AiSearchIndexer, never edited: this table is a cache of the site's own pages, so it holds nothing another site or a signed-in visitor could have seen. The FULLTEXT index is MariaDB's own, which a site on a version without the VECTOR type still has
#[ORM\Entity(repositoryClass: AiSearchChunkRepository::class)]
#[ORM\Table(name: 'site_ai_search_chunk')]
#[ORM\Index(name: 'idx_ai_search_chunk_locale', columns: ['locale'])]
#[ORM\Index(name: 'idx_ai_search_chunk_text', columns: ['title', 'content'], flags: ['fulltext'])]
class AiSearchChunk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 500)]
        private string $url,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(type: 'text')]
        private string $content,
        #[ORM\Column(length: 10)]
        private string $locale,
        // Hash of the whole index this passage was written with: an answer recorded under another one was built from pages that have since changed, and is asked again rather than served
        #[ORM\Column(length: 40)]
        private string $indexVersion,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getIndexVersion(): string
    {
        return $this->indexVersion;
    }
}
