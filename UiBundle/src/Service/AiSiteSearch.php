<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\AiSearchAnswer;
use c975L\UiBundle\Repository\AiSearchAnswerRepository;
use c975L\UiBundle\Repository\AiSearchChunkRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

// Answers a visitor's question about the site: the recorded answer when the same question was already asked against the current index, otherwise the closest passages of the index handed to the site's LLM. The model is only ever called with passages in hand - a question the index has nothing close to is answered "not found" for free - and the links it is shown come from the index alone, the model only saying which of the passages it used
class AiSiteSearch
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 300;

    // Days an answer nobody asks again is kept, when "ui-ai-assistant-site-retention-days" is empty or not positive
    public const int DEFAULT_RETENTION_DAYS = 90;

    // Passages sent with a question, about 2,000 tokens at AiSearchPageReader::CHUNK_LENGTH
    private const int EXCERPTS = 6;

    public function __construct(
        private readonly AiSiteSearchClient $client,
        private readonly AiSearchChunkRepository $chunkRepository,
        private readonly AiSearchAnswerRepository $answerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    // On as soon as the four entries are filled in and the index holds something to answer from
    public function isEnabled(): bool
    {
        return $this->client->isEnabled() && null !== $this->chunkRepository->currentVersion();
    }

    // Null when the search is off or the provider failed, which the visitor is told apart from "nothing found"
    /** @return array{answer: string, sources: list<array{url: string, title: string}>, found: bool}|null */
    public function ask(string $question, string $locale): ?array
    {
        $question = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $question)), 0, self::MAX_LENGTH);
        $version = $this->chunkRepository->currentVersion();
        if (!$this->client->isEnabled() || null === $version || mb_strlen($question) < self::MIN_LENGTH) {
            return null;
        }

        $hash = hash('sha256', $locale . '|' . mb_strtolower($question));
        $recorded = $this->answerRepository->findOneByQuestionHash($hash);
        if (null !== $recorded && $recorded->getIndexVersion() === $version) {
            $recorded->recordHit();
            $this->entityManager->flush();

            return $this->result($recorded);
        }

        $excerpts = $this->chunkRepository->search($question, $locale, self::EXCERPTS);
        $answer = '';
        $sources = [];
        if ([] !== $excerpts) {
            $reply = $this->client->answer($question, $excerpts, (string) $this->configService->get('site-name'), $locale);
            if (null === $reply) {
                return null;
            }

            [$answer, $sources] = $this->parse($reply, $excerpts);
        }

        // Read again: a spend recorded concurrently may have reset the manager during the provider call, detaching the row read above
        $recorded = $this->answerRepository->findOneByQuestionHash($hash) ?? new AiSearchAnswer($hash, $question, $locale);
        $recorded->recordAnswer($answer, $sources, [] !== $sources, $version);

        // The same question asked at the same instant by someone else: their row stands, this visitor still gets the answer paid for
        try {
            $this->entityManager->persist($recorded);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->managerRegistry->resetManager();
        }

        return $this->result($recorded);
    }

    // Answers not asked again within the retention, returning how many went
    public function purge(): int
    {
        $days = self::retentionDays($this->configService->get('ui-ai-assistant-site-retention-days'));

        return $this->answerRepository->deleteNotAskedSince(new \DateTimeImmutable('-' . $days . ' days'));
    }

    // The one reading of "ui-ai-assistant-site-retention-days", shared with the privacy policy that states it (see LegalModelPlaceholders)
    public static function retentionDays(mixed $configured): int
    {
        $days = is_numeric($configured) ? (int) $configured : 0;

        return $days > 0 ? $days : self::DEFAULT_RETENTION_DAYS;
    }

    // The answer and the passages it names, mapped back to the index: a number that names no passage is dropped, so no url the model wrote ever reaches the page. A reply that isn't the JSON asked for is kept as the answer, with the pages of the passages as its sources
    /**
     * @param list<array{url: string, title: string, content: string}> $excerpts
     *
     * @return array{0: string, 1: list<array{url: string, title: string}>}
     */
    private function parse(string $reply, array $excerpts): array
    {
        $data = preg_match('/\{.*\}/s', $reply, $matches) ? json_decode($matches[0], true) : null;
        if (!\is_array($data) || !\is_string($data['answer'] ?? null)) {
            return [trim(strip_tags($reply)), $this->sources($excerpts)];
        }

        $used = [];
        foreach ((array) ($data['sources'] ?? []) as $number) {
            if (is_numeric($number) && isset($excerpts[(int) $number - 1])) {
                $used[] = $excerpts[(int) $number - 1];
            }
        }

        return [trim(strip_tags($data['answer'])), $this->sources($used)];
    }

    // One link per page, in the order the passages came
    // @param list<array{url: string, title: string, content: string}> $excerpts
    // @return list<array{url: string, title: string}>
    private function sources(array $excerpts): array
    {
        $sources = [];
        foreach ($excerpts as $excerpt) {
            $sources[$excerpt['url']] ??= ['url' => $excerpt['url'], 'title' => $excerpt['title']];
        }

        return array_values($sources);
    }

    // @return array{answer: string, sources: list<array{url: string, title: string}>, found: bool}
    private function result(AiSearchAnswer $answer): array
    {
        return [
            'answer' => $answer->getAnswer(),
            'sources' => $answer->getSources(),
            'found' => $answer->isFound(),
        ];
    }
}
