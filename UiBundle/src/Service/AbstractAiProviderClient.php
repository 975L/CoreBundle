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

// Calls an LLM with the site's own key, for every feature billed to it: the rephrase and the site search differ by their config prefix and their prompts alone. Anthropic is called with its native API, anything else as an OpenAI-compatible one (OpenAI itself, or Infomaniak's Euria, whose only difference is its base URI). Nothing is defaulted in code: an empty entry keeps the feature off rather than calling an address or a model nobody chose
abstract class AbstractAiProviderClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
        private readonly LoggerInterface $logger,
        private readonly AiUsageTracker $aiUsageTracker,
    ) {
    }

    // Prefix of the four config entries ("<prefix>-provider", "-api-key", "-base-uri", "-model")
    abstract protected function configPrefix(): string;

    // AiUsage::FEATURE_* the spend and the failures are recorded under
    abstract protected function feature(): string;

    public function isEnabled(): bool
    {
        return array_all(
            ['provider', 'api-key', 'base-uri', 'model'],
            fn (string $entry): bool => (bool) $this->config($entry),
        );
    }

    // The one place a provider is called. Null when disabled or on failure, which is logged and recorded so AiAlertProvider can raise it
    protected function send(string $prompt, ?string $system = null, int $maxTokens = 2048): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $provider = $this->config('provider');

        try {
            return match ($provider) {
                'anthropic' => $this->callAnthropic($prompt, $system, $maxTokens),
                'openai', 'euria' => $this->callOpenAiCompatible($prompt, $system),
                default => null,
            };
        } catch (ExceptionInterface $e) {
            // The response body carries the provider's own detail, far more actionable than "HTTP 400"
            $message = $e->getMessage();
            if ($e instanceof HttpExceptionInterface) {
                $message .= ' ' . $e->getResponse()->getContent(false);
            }

            $this->logger->error('AI request failed ({feature}): {message}', ['feature' => $this->feature(), 'message' => $message]);
            $this->aiUsageTracker->recordFailure($message, $this->feature());

            return null;
        }
    }

    private function config(string $entry): string
    {
        return (string) $this->configService->get($this->configPrefix() . '-' . $entry);
    }

    private function callAnthropic(string $prompt, ?string $system, int $maxTokens): string
    {
        $json = [
            'model' => $this->config('model'),
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        if (null !== $system) {
            $json['system'] = $system;
        }

        $response = $this->httpClient->request('POST', $this->config('base-uri'), [
            'headers' => [
                'x-api-key' => $this->config('api-key'),
                'anthropic-version' => '2023-06-01',
            ],
            'json' => $json,
            'timeout' => 20,
        ]);

        $data = $response->toArray();
        $this->aiUsageTracker->record(
            (int) ($data['usage']['input_tokens'] ?? 0),
            (int) ($data['usage']['output_tokens'] ?? 0),
            $this->feature(),
        );

        return (string) ($data['content'][0]['text'] ?? '');
    }

    // Covers both OpenAI and Euria (Infomaniak AI Tools), called on the base URI their own entry carries. No token ceiling: OpenAI's reasoning models refuse "max_tokens", and a long translation must not be cut
    private function callOpenAiCompatible(string $prompt, ?string $system): string
    {
        $messages = null !== $system ? [['role' => 'system', 'content' => $system]] : [];
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = $this->httpClient->request('POST', rtrim($this->config('base-uri'), '/') . '/chat/completions', [
            'auth_bearer' => $this->config('api-key'),
            'json' => [
                'model' => $this->config('model'),
                'messages' => $messages,
            ],
            'timeout' => 20,
        ]);

        $data = $response->toArray();
        $this->aiUsageTracker->record(
            (int) ($data['usage']['prompt_tokens'] ?? 0),
            (int) ($data['usage']['completion_tokens'] ?? 0),
            $this->feature(),
        );

        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }
}
