<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use <?= $repository_full_name ?>;
use c975L\UiBundle\Doctrine\VectorType;
use Doctrine\ORM\Mapping as ORM;

// Answer cache: an exact-hash hit skips the LLM, a miss tries a semantic match first contextVersion pins an answer to its context, a stale one counting as a miss
#[ORM\Entity(repositoryClass: <?= $repository_short_name ?>::class)]
class <?= $class_name ?>
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $questionHash = '';

    #[ORM\Column(type: 'text')]
    private string $questionText = '';

    #[ORM\Column(type: 'text')]
    private string $answerText = '';

    #[ORM\Column(length: 32)]
    private string $contextVersion = '';

    #[ORM\Column]
    private int $hitCount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $inputTokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $outputTokens = null;

    /** @var array{label: string, url: string}[] */
    #[ORM\Column(type: 'json')]
    private array $sources = [];

    // Null until the embedding model is configured, or if that call fails
    /** @var float[]|null */
    #[ORM\Column(type: VectorType::NAME, nullable: true)]
    private ?array $questionEmbedding = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestionHash(): string
    {
        return $this->questionHash;
    }

    public function getQuestionText(): string
    {
        return $this->questionText;
    }

    public function getAnswerText(): string
    {
        return $this->answerText;
    }

    public function getContextVersion(): string
    {
        return $this->contextVersion;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function getInputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): ?int
    {
        return $this->outputTokens;
    }

    /** @return array{label: string, url: string}[] */
    public function getSources(): array
    {
        return $this->sources;
    }

    /** @return float[]|null */
    public function getQuestionEmbedding(): ?array
    {
        return $this->questionEmbedding;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Cache-miss path, overwriting in place and resetting hitCount; 0 tokens on a semantic-match reuse
    /**
     * @param array{label: string, url: string}[] $sources
     * @param float[]|null $questionEmbedding
     */
    public function recordFreshAnswer(
        string $questionHash,
        string $questionText,
        string $answerText,
        string $contextVersion,
        int $inputTokens,
        int $outputTokens,
        array $sources,
        ?array $questionEmbedding = null,
    ): static {
        $this->questionHash = $questionHash;
        $this->questionText = $questionText;
        $this->answerText = $answerText;
        $this->contextVersion = $contextVersion;
        $this->hitCount = 0;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
        $this->sources = $sources;
        $this->questionEmbedding = $questionEmbedding;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    // Cache-hit path - never touches inputTokens/outputTokens, so a hit never inflates the cost sum
    public function recordHit(): static
    {
        ++$this->hitCount;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
