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

    // The one place a provider is called. Null when disabled or on failure, which is logged and recorded so AiAlertProvider can raise it. $images are data URIs ("data:image/jpeg;base64,...") read along with the prompt, for a model that sees - protected like the rest, so only a feature written for it ever sends one
    /** @param list<string> $images */
    protected function send(string $prompt, ?string $system = null, int $maxTokens = 2048, array $images = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $provider = $this->config('provider');

        try {
            return match ($provider) {
                'anthropic' => $this->callAnthropic($prompt, $system, $maxTokens, $images),
                'openai', 'euria' => $this->callOpenAiCompatible($prompt, $system, $images),
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

    /** @param list<string> $images */
    private function callAnthropic(string $prompt, ?string $system, int $maxTokens, array $images): string
    {
        // A plain string while there is no image, the request every existing feature has always sent
        $content = $prompt;
        if ([] !== $images) {
            $content = [];
            foreach ($images as $image) {
                [$mediaType, $data] = self::splitDataUri($image);
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $data]];
            }
            $content[] = ['type' => 'text', 'text' => $prompt];
        }

        $json = [
            'model' => $this->config('model'),
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $content],
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
            'timeout' => self::timeout($images),
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
    /** @param list<string> $images */
    private function callOpenAiCompatible(string $prompt, ?string $system, array $images): string
    {
        $content = $prompt;
        if ([] !== $images) {
            $content = [['type' => 'text', 'text' => $prompt]];
            foreach ($images as $image) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
            }
        }

        $messages = null !== $system ? [['role' => 'system', 'content' => $system]] : [];
        $messages[] = ['role' => 'user', 'content' => $content];

        $response = $this->httpClient->request('POST', rtrim($this->config('base-uri'), '/') . '/chat/completions', [
            'auth_bearer' => $this->config('api-key'),
            'json' => [
                'model' => $this->config('model'),
                'messages' => $messages,
            ],
            'timeout' => self::timeout($images),
        ]);

        $data = $response->toArray();
        $this->aiUsageTracker->record(
            (int) ($data['usage']['prompt_tokens'] ?? 0),
            (int) ($data['usage']['completion_tokens'] ?? 0),
            $this->feature(),
        );

        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }

    // Idle timeout: a model reading a photo takes longer before its first byte than one reading a sentence
    /** @param list<string> $images */
    private static function timeout(array $images): int
    {
        return [] === $images ? 20 : 60;
    }

    // "data:image/jpeg;base64,AAAA" split into its media type and its data, the two fields Anthropic asks for apart
    /** @return array{0: string, 1: string} */
    private static function splitDataUri(string $image): array
    {
        if (1 !== preg_match('#^data:(?<type>[\w/+.-]+);base64,(?<data>.+)$#s', $image, $matches)) {
            throw new \InvalidArgumentException('An image is sent to the AI provider as a base64 data URI.');
        }

        return [$matches['type'], $matches['data']];
    }
}
