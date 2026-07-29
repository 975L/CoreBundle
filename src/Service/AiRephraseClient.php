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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Rephrases free text using the client's own key ("ui-ai-assistant-rephrase-*" config, distinct from the dashboard assistant's key) - stateless, nothing is ever persisted or logged beyond the request itself. Supports Anthropic and any OpenAI-compatible API (OpenAI itself, or Infomaniak's Euria, whose only difference from OpenAI is its base URI). No interface here (unlike AiAssistantClient): there's nothing to override, a consuming app not wanting this feature simply leaves the api-key config empty. Token counts from each response are handed to AiUsageTracker - a numeric count alone reveals nothing about the rephrased content, so this doesn't compromise the "nothing is persisted" promise above
class AiRephraseClient
{
    private const ANTHROPIC_URI = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_DEFAULT_MODEL = 'claude-haiku-4-5';
    private const OPENAI_URI = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_DEFAULT_MODEL = 'gpt-4o-mini';
    // No euria default: its catalog isn't static enough, so the model config is required by isEnabled()

    // Closed list: $style indexes this map, so a request parameter can never inject its own instructions
    private const STYLES = [
        'neutral' => '',
        'professional' => ' Use a formal, professional tone.',
        'friendly' => ' Use a warm, friendly, conversational tone.',
        'concise' => ' Be as concise as possible while keeping the meaning intact.',
        'persuasive' => ' Use a persuasive, compelling tone that encourages action.',
        'simple' => ' Use simple words and short sentences, easy to understand for a broad audience.',
        'enthusiastic' => ' Use an enthusiastic, energetic tone.',
        'expanded' => ' Expand the text with more detail and context, while keeping the same meaning.',
    ];

    // Same closed list as STYLES; an explicit paragraph count, a relative "longer" being unpredictable
    private const LENGTHS = [
        'same' => ' Keep approximately the same length.',
        'paragraph_1' => ' Rewrite it as exactly one paragraph.',
        'paragraph_2' => ' Rewrite it as exactly two paragraphs, separated by a blank line.',
        'paragraph_3' => ' Rewrite it as exactly three paragraphs, separated by a blank line.',
        'paragraph_4' => ' Rewrite it as exactly four paragraphs, separated by a blank line.',
        'social_summary' => ' Condense it into a single summary of at most 155 characters, meant to be used as a meta description and as the text of a social network share card: plain text on one line, no quotes, no hashtags, no emoji, no trailing ellipsis.',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
        private readonly LoggerInterface $logger,
        private readonly AiUsageTracker $aiUsageTracker,
    ) {
    }

    public function isEnabled(): bool
    {
        $provider = $this->configService->get('ui-ai-assistant-rephrase-provider');

        if (null === $provider || null === $this->configService->get('ui-ai-assistant-rephrase-api-key')) {
            return false;
        }

        // Only euria needs its own base URI and model, the others calling a fixed URI with a safe default
        if ('euria' === $provider && (
            !$this->configService->get('ui-ai-assistant-rephrase-base-uri')
            || !$this->configService->get('ui-ai-assistant-rephrase-model')
        )) {
            return false;
        }

        return true;
    }

    // @return string[] Style keys accepted by rephrase(), for a caller building a selector
    public function getStyles(): array
    {
        return array_keys(self::STYLES);
    }

    // @return string[] Length keys accepted by rephrase(), for a caller building a selector
    public function getLengths(): array
    {
        return array_keys(self::LENGTHS);
    }

    public function rephrase(string $text, string $style = 'neutral', string $length = 'same'): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $provider = (string) $this->configService->get('ui-ai-assistant-rephrase-provider');
        $apiKey = (string) $this->configService->get('ui-ai-assistant-rephrase-api-key');
        $styleInstruction = self::STYLES[$style] ?? self::STYLES['neutral'];
        $lengthInstruction = self::LENGTHS[$length] ?? self::LENGTHS['same'];

        try {
            return match ($provider) {
                'anthropic' => $this->callAnthropic($text, $apiKey, $styleInstruction, $lengthInstruction),
                'openai', 'euria' => $this->callOpenAiCompatible($text, $apiKey, $provider, $styleInstruction, $lengthInstruction),
                default => null,
            };
        } catch (ExceptionInterface $e) {
            // The response body carries the provider's own detail, far more actionable than "HTTP 400"
            $message = $e->getMessage();
            if ($e instanceof HttpExceptionInterface) {
                $message .= ' ' . $e->getResponse()->getContent(false);
            }

            $this->logger->error('AI rephrase request failed: {message}', ['message' => $message]);
            $this->aiUsageTracker->recordFailure($message);

            return null;
        }
    }

    private function callAnthropic(string $text, string $apiKey, string $styleInstruction, string $lengthInstruction): ?string
    {
        $model = $this->configService->get('ui-ai-assistant-rephrase-model') ?: self::ANTHROPIC_DEFAULT_MODEL;

        $response = $this->httpClient->request('POST', self::ANTHROPIC_URI, [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => $model,
                // Required by Anthropic; roomy enough that the longest LENGTHS entry isn't cut off
                'max_tokens' => 2048,
                'messages' => [
                    ['role' => 'user', 'content' => $this->prompt($text, $styleInstruction, $lengthInstruction)],
                ],
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray();
        $this->aiUsageTracker->record(
            (int) ($data['usage']['input_tokens'] ?? 0),
            (int) ($data['usage']['output_tokens'] ?? 0),
        );

        return (string) ($data['content'][0]['text'] ?? '');
    }

    // Covers both OpenAI and Euria (Infomaniak AI Tools): Euria exposes an OpenAI-compatible API, only the base URI differs, read from "ui-ai-assistant-rephrase-base-uri"
    private function callOpenAiCompatible(string $text, string $apiKey, string $provider, string $styleInstruction, string $lengthInstruction): ?string
    {
        $isOpenAi = 'openai' === $provider;
        $uri = $isOpenAi
            ? self::OPENAI_URI
            : rtrim((string) $this->configService->get('ui-ai-assistant-rephrase-base-uri'), '/') . '/chat/completions';
        // Euria always has a model set by now, enforced by isEnabled(); openai has a stable default
        $model = $isOpenAi
            ? ($this->configService->get('ui-ai-assistant-rephrase-model') ?: self::OPENAI_DEFAULT_MODEL)
            : (string) $this->configService->get('ui-ai-assistant-rephrase-model');

        $response = $this->httpClient->request('POST', $uri, [
            'auth_bearer' => $apiKey,
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $this->prompt($text, $styleInstruction, $lengthInstruction)],
                ],
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray();
        $this->aiUsageTracker->record(
            (int) ($data['usage']['prompt_tokens'] ?? 0),
            (int) ($data['usage']['completion_tokens'] ?? 0),
        );

        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }

    private function prompt(string $text, string $styleInstruction, string $lengthInstruction): string
    {
        return "Rephrase the following text, keeping its original language and meaning."
            . $lengthInstruction
            . $styleInstruction
            . " Return only the rephrased text, nothing else:\n\n" . $text;
    }
}
