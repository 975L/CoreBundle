<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use <?= $entity_full_name ?>;
use <?= $repository_full_name ?>;
use <?= $context_builder_full_name ?>;
use <?= $llm_client_full_name ?>;
use <?= $embedding_client_full_name ?>;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

// Cache-or-call orchestration: exact hash, then semantic cache, the LLM only as a last resort
class <?= $class_name ?>
{
    public function __construct(
        private readonly <?= $repository_short_name ?> $repository,
        private readonly <?= $context_builder_short_name ?> $contextBuilder,
        private readonly <?= $llm_client_short_name ?> $llmClient,
        private readonly <?= $embedding_client_short_name ?> $embeddingClient,
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{answer: string, sources: array{label: string, url: string}[]}|null
     */
    public function ask(string $question): ?array
    {
        $normalized = $this->normalize($question);
        $hash = hash('sha256', $normalized);
        // TODO: fixed for now, so a cached answer is never invalidated on its own merit; add a version() to the context builder
        $version = 'v1';

        $existing = $this->repository->findOneByQuestionHash($hash);

        if (null !== $existing && $existing->getContextVersion() === $version) {
            $existing->recordHit();
            $this->entityManager->flush();

            return ['answer' => $existing->getAnswerText(), 'sources' => $existing->getSources()];
        }

        $questionEmbedding = $this->embeddingClient->embed($normalized);

        // Persisted under this question's own hash, so a literal repeat takes the exact-hash path
        if (null !== $questionEmbedding) {
            $semanticMatch = $this->repository->findBestSemanticMatch($questionEmbedding, $version, $this->semanticThreshold());

            if (null !== $semanticMatch) {
                $answer = ($existing ?? new <?= $entity_short_name ?>())->recordFreshAnswer(
                    $hash,
                    $normalized,
                    $semanticMatch->getAnswerText(),
                    $version,
                    0,
                    0,
                    $semanticMatch->getSources(),
                    $questionEmbedding,
                );

                $this->entityManager->persist($answer);
                $this->entityManager->flush();

                return ['answer' => $answer->getAnswerText(), 'sources' => $answer->getSources()];
            }
        }

        $result = $this->llmClient->ask($normalized, $this->contextBuilder->context());
        if (null === $result) {
            return null;
        }

        $sources = $this->contextBuilder->resolveSources($result['sourceKinds']);

        $answer = ($existing ?? new <?= $entity_short_name ?>())->recordFreshAnswer(
            $hash,
            $normalized,
            $result['answer'],
            $version,
            $result['inputTokens'],
            $result['outputTokens'],
            $sources,
            $questionEmbedding,
        );

        $this->entityManager->persist($answer);
        $this->entityManager->flush();

        return ['answer' => $answer->getAnswerText(), 'sources' => $answer->getSources()];
    }

    private function normalize(string $question): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($question)));
    }

    private function semanticThreshold(): float
    {
        return (float) ($this->configService->get('donovan-qa-semantic-cache-threshold') ?? 0.90);
    }
}
