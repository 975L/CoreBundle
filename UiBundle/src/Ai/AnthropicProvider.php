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

// Anthropic's native Messages API, called on the base URI the feature's entry carries
class AnthropicProvider implements AiProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getSlug(): string
    {
        return 'anthropic';
    }

    public function complete(string $prompt, ?string $system, int $maxTokens, array $images, array $settings): array
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
            'model' => $settings['model'],
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];
        if (null !== $system) {
            $json['system'] = $system;
        }

        $data = $this->httpClient->request('POST', $settings['base-uri'], [
            'headers' => [
                'x-api-key' => $settings['api-key'],
                'anthropic-version' => '2023-06-01',
            ],
            'json' => $json,
            'timeout' => [] === $images ? self::TIMEOUT : self::TIMEOUT_WITH_IMAGES,
        ])->toArray();

        return [
            'text' => (string) ($data['content'][0]['text'] ?? ''),
            'inputTokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'outputTokens' => (int) ($data['usage']['output_tokens'] ?? 0),
            'truncated' => 'max_tokens' === ($data['stop_reason'] ?? null),
        ];
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
