<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\AiUsageRepository;
use Doctrine\ORM\Mapping as ORM;

// Tracks the token spend of each AI feature paid for by the site's own key (FEATURE_*), one row per feature and calendar month - AiRephraseClient calls the provider directly (no intermediary), so this is the only place that spend is ever visible. Aggregated at month granularity on purpose, not one row per request: a per-request log would tie a token count to a timestamp close enough to correlate with a specific edit, undermining the "nothing is persisted" promise for the rephrased content itself, even though the count alone reveals nothing about it
#[ORM\Entity(repositoryClass: AiUsageRepository::class)]
#[ORM\Table(name: 'site_ai_usage')]
#[ORM\UniqueConstraint(name: 'uniq_ai_usage_feature_month', columns: ['feature', 'year_month'])]
class AiUsage
{
    public const string FEATURE_REPHRASE = 'rephrase';
    public const string FEATURE_SITE_SEARCH = 'site_search';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // "YYYY-MM"; backtick-quoted because "year_month" is a reserved MariaDB keyword the ORM's INSERT wouldn't quote
    #[ORM\Column(name: '`year_month`', length: 7)]
    private string $yearMonth;

    // Each feature has its own row, so a failing site search never raises the rephrase alert nor mixes into its spend. Defaulted so the rows written before this column existed stay the rephrase's
    #[ORM\Column(length: 20, options: ['default' => self::FEATURE_REPHRASE])]
    private string $feature = self::FEATURE_REPHRASE;

    #[ORM\Column]
    private int $inputTokens = 0;

    #[ORM\Column]
    private int $outputTokens = 0;

    #[ORM\Column]
    private int $requestCount = 0;

    // Cleared on the next successful call (see addUsage()) - AiAlertProvider surfaces a warning on the admin dashboard while this is set, so a broken key doesn't fail silently until an editor notices
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastFailureAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastFailureMessage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYearMonth(): string
    {
        return $this->yearMonth;
    }

    public function setYearMonth(string $yearMonth): static
    {
        $this->yearMonth = $yearMonth;

        return $this;
    }

    public function getFeature(): string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): static
    {
        $this->feature = $feature;

        return $this;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function addUsage(int $inputTokens, int $outputTokens): static
    {
        $this->inputTokens += $inputTokens;
        $this->outputTokens += $outputTokens;
        ++$this->requestCount;
        $this->clearFailure();

        return $this;
    }

    public function getLastFailureAt(): ?\DateTimeImmutable
    {
        return $this->lastFailureAt;
    }

    public function getLastFailureMessage(): ?string
    {
        return $this->lastFailureMessage;
    }

    public function recordFailure(string $message): static
    {
        $this->lastFailureAt = new \DateTimeImmutable();
        $this->lastFailureMessage = $message;

        return $this;
    }

    public function clearFailure(): static
    {
        $this->lastFailureAt = null;
        $this->lastFailureMessage = null;

        return $this;
    }
}
