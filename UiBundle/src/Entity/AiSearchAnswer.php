<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\AiSearchAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

// A question a visitor asked the site search and what it answered, kept so the same question is not paid for twice and the admin sees what is searched for - and what found nothing. No ip, no session, no account: the question alone, purged after "ui-ai-assistant-site-retention-days" since a visitor may well have typed something personal into it
#[ORM\Entity(repositoryClass: AiSearchAnswerRepository::class)]
#[ORM\Table(name: 'site_ai_search_answer')]
#[ORM\Index(name: 'idx_ai_search_answer_updated_at', columns: ['updated_at'])]
class AiSearchAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $answer = '';

    // @var list<array{url: string, title: string}> Only urls of the index, never one the model wrote
    #[ORM\Column(type: 'json')]
    private array $sources = [];

    // False when the index held nothing close to the question, or the model found nothing in it: the list the admin reads to know what the site lacks
    #[ORM\Column]
    private bool $found = false;

    #[ORM\Column(length: 40)]
    private string $indexVersion = '';

    #[ORM\Column]
    private int $hitCount = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        // sha256 of the locale and the normalized question
        #[ORM\Column(length: 64, unique: true)]
        private string $questionHash,
        #[ORM\Column(length: 300)]
        private string $question,
        #[ORM\Column(length: 10)]
        private string $locale,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestionHash(): string
    {
        return $this->questionHash;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    // @return list<array{url: string, title: string}>
    public function getSources(): array
    {
        return $this->sources;
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    public function getIndexVersion(): string
    {
        return $this->indexVersion;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // An answer built against the current index, replacing whatever an earlier one said
    // @param list<array{url: string, title: string}> $sources
    public function recordAnswer(string $answer, array $sources, bool $found, string $indexVersion): static
    {
        $this->answer = $answer;
        $this->sources = $sources;
        $this->found = $found;
        $this->indexVersion = $indexVersion;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    // The same question served again from here, at no cost
    public function recordHit(): static
    {
        ++$this->hitCount;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
