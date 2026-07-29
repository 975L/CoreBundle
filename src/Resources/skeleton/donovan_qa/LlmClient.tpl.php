<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Calls the LLM answering the Q&A questions: Anthropic's native API, or any OpenAI-compatible one
class <?= $class_name ?>
{
    private const ANTHROPIC_DEFAULT_URI = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_DEFAULT_MODEL = 'claude-haiku-4-5';
    private const MAX_TOKENS = 1024;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        // Explicit master switch, so the feature toggles off without clearing credentials
        if (true !== $this->configService->get('donovan-qa-llm-enabled')) {
            return false;
        }

        if (null === $this->configService->get('donovan-qa-llm-api-key')) {
            return false;
        }

        // Euria has no safe default model or base URI, so both must be set explicitly
        $provider = (string) ($this->configService->get('donovan-qa-llm-provider') ?: 'anthropic');
        if ('euria' === $provider && (
            !$this->configService->get('donovan-qa-llm-model')
            || !$this->configService->get('donovan-qa-llm-base-uri')
        )) {
            return false;
        }

        return true;
    }

    // @return array{answer: string, sourceKinds: string[], inputTokens: int, outputTokens: int}|null
    public function ask(string $question, string $context): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $provider = (string) ($this->configService->get('donovan-qa-llm-provider') ?: 'anthropic');
        $apiKey = (string) $this->configService->get('donovan-qa-llm-api-key');

        try {
            return match ($provider) {
                'anthropic' => $this->callAnthropic($question, $context, $apiKey),
                'euria' => $this->callEuria($question, $context, $apiKey),
                default => null,
            };
        } catch (ExceptionInterface $e) {
            $this->logger->error('Donovan Q&A request failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function callAnthropic(string $question, string $context, string $apiKey): array
    {
        $uri = $this->configService->get('donovan-qa-llm-base-uri') ?: self::ANTHROPIC_DEFAULT_URI;
        $model = $this->configService->get('donovan-qa-llm-model') ?: self::ANTHROPIC_DEFAULT_MODEL;

        $response = $this->httpClient->request('POST', $uri, [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => $model,
                'max_tokens' => self::MAX_TOKENS,
                'system' => $this->systemPrompt($context),
                'messages' => [
                    ['role' => 'user', 'content' => $question],
                ],
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray();
        $parsed = $this->parseSourcedAnswer((string) ($data['content'][0]['text'] ?? ''));

        return [
            'answer' => $parsed['answer'],
            'sourceKinds' => $parsed['sourceKinds'],
            'inputTokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'outputTokens' => (int) ($data['usage']['output_tokens'] ?? 0),
        ];
    }

    // Euria (Infomaniak AI Tools) exposes an OpenAI-compatible chat completions API
    private function callEuria(string $question, string $context, string $apiKey): array
    {
        $uri = rtrim((string) $this->configService->get('donovan-qa-llm-base-uri'), '/') . '/chat/completions';
        // Always set at this point - isEnabled() requires "donovan-qa-llm-model" for euria
        $model = (string) $this->configService->get('donovan-qa-llm-model');

        $response = $this->httpClient->request('POST', $uri, [
            'auth_bearer' => $apiKey,
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($context)],
                    ['role' => 'user', 'content' => $question],
                ],
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray();
        $parsed = $this->parseSourcedAnswer((string) ($data['choices'][0]['message']['content'] ?? ''));

        return [
            'answer' => $parsed['answer'],
            'sourceKinds' => $parsed['sourceKinds'],
            'inputTokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'outputTokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
        ];
    }

    private function systemPrompt(string $context): string
    {
        return "You are the admin dashboard assistant. Answer only from the following context, which documents the available blocks. If the question is unrelated, say so plainly rather than inventing anything outside this context.\n\n"
            . "Always end your answer with a line exactly formatted as \"SOURCES: kind1, kind2\" listing the kind identifiers (the word after \"###\" in the context) you relied on, or \"SOURCES: none\" if no specific kind applies.\n\n"
            . $context;
    }

    // Splits the trailing "SOURCES:" line off; a malformed one degrades to zero sources, never a failure
    private function parseSourcedAnswer(string $rawAnswer): array
    {
        if (!preg_match('/^(.*?)\n*SOURCES:\s*(.*)$/is', trim($rawAnswer), $matches)) {
            return ['answer' => trim($rawAnswer), 'sourceKinds' => []];
        }

        $kinds = array_filter(
            array_map('trim', explode(',', $matches[2])),
            static fn (string $kind): bool => '' !== $kind && 'none' !== strtolower($kind),
        );

        return ['answer' => trim($matches[1]), 'sourceKinds' => array_values($kinds)];
    }
}
