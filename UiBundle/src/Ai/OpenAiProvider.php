<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Ai;

use c975L\UiBundle\Contract\AiProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// OpenAI's chat completions, and any API compatible with it (see EuriaProvider), called on the base URI the feature's entry carries. The ceiling goes as "max_completion_tokens", which OpenAI's reasoning models take where they refuse "max_tokens" (see ceilingParameter()); a feature with long answers (a translation) asks for a wide one
class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getSlug(): string
    {
        return 'openai';
    }

    public function complete(string $prompt, ?string $system, int $maxTokens, array $images, array $settings): array
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

        // What one call may cost at most, on a public and anonymous feature such as the site search
        $json = ['model' => $settings['model'], 'messages' => $messages, $this->ceilingParameter() => $maxTokens];
        $effort = $this->lowestReasoningEffort($settings['model']);
        if (null !== $effort) {
            $json['reasoning_effort'] = $effort;
        }

        $data = $this->httpClient->request('POST', rtrim($settings['base-uri'], '/') . '/chat/completions', [
            'auth_bearer' => $settings['api-key'],
            'json' => $json,
            'timeout' => [] === $images ? self::TIMEOUT : self::TIMEOUT_WITH_IMAGES,
        ])->toArray();

        return [
            'text' => (string) ($data['choices'][0]['message']['content'] ?? ''),
            'inputTokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'outputTokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            'truncated' => 'length' === ($data['choices'][0]['finish_reason'] ?? null),
        ];
    }

    // The least a reasoning model thinks before it answers, its hidden reasoning counting against the ceiling: "minimal" for gpt-5, "none" for gpt-5.1 and later, "low" for the o series, null for a model that does not reason
    protected function lowestReasoningEffort(string $model): ?string
    {
        return match (true) {
            1 === preg_match('/^gpt-5(-|$)/', $model) && !str_contains($model, '-chat') => 'minimal',
            1 === preg_match('/^gpt-5\.\d/', $model) && !str_contains($model, '-chat') => 'none',
            1 === preg_match('/^o\d/', $model) => 'low',
            default => null,
        };
    }

    // The name the API gives the answer's ceiling
    protected function ceilingParameter(): string
    {
        return 'max_completion_tokens';
    }
}
