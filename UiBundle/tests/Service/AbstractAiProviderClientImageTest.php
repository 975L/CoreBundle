<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\AbstractAiProviderClient;
use c975L\UiBundle\Service\AiUsageTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// A photo sent along with the prompt, in the shape each provider expects - and a prompt without one sent exactly as before
class AbstractAiProviderClientImageTest extends TestCase
{
    private const string IMAGE = 'data:image/jpeg;base64,AAAA';

    public function testEuriaReceivesTheImageAsAnImageUrl(): void
    {
        $body = null;
        $client = $this->createClient('euria', 'https://api.infomaniak.com/2/ai/1/openai/v1', $body, '{"choices":[{"message":{"content":"ICI FUT FUSILLÉ"}}]}');

        $this->assertSame('ICI FUT FUSILLÉ', $client->ask('Transcribe', [self::IMAGE]));
        $this->assertSame([
            ['type' => 'text', 'text' => 'Transcribe'],
            ['type' => 'image_url', 'image_url' => ['url' => self::IMAGE]],
        ], $body['messages'][0]['content']);
    }

    public function testAnthropicReceivesTheImageAsABase64Source(): void
    {
        $body = null;
        $client = $this->createClient('anthropic', 'https://api.anthropic.com/v1/messages', $body, '{"content":[{"text":"ICI FUT FUSILLÉ"}]}');

        $this->assertSame('ICI FUT FUSILLÉ', $client->ask('Transcribe', [self::IMAGE]));
        $this->assertSame([
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => 'AAAA']],
            ['type' => 'text', 'text' => 'Transcribe'],
        ], $body['messages'][0]['content']);
    }

    public function testAPromptWithoutImageIsStillAPlainString(): void
    {
        $body = null;
        $client = $this->createClient('euria', 'https://api.infomaniak.com/2/ai/1/openai/v1', $body, '{"choices":[{"message":{"content":"ok"}}]}');

        $client->ask('Rephrase', []);

        $this->assertSame('Rephrase', $body['messages'][0]['content']);
    }

    // A feature that sends a photo, written the way a site would write its own; $body receives what reached the provider
    private function createClient(string $provider, string $baseUri, ?array &$body, string $answer): object
    {
        $values = [
            'test-image-provider' => $provider,
            'test-image-api-key' => 'key',
            'test-image-base-uri' => $baseUri,
            'test-image-model' => 'a-model',
        ];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $values[$key] ?? null);

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body, $answer): MockResponse {
            $body = json_decode($options['body'], true);

            return new MockResponse($answer);
        });

        return new class ($httpClient, $configService, $this->createStub(LoggerInterface::class), $this->createStub(AiUsageTracker::class)) extends AbstractAiProviderClient {
            protected function configPrefix(): string
            {
                return 'test-image';
            }

            protected function feature(): string
            {
                return 'test_image';
            }

            /** @param list<string> $images */
            public function ask(string $prompt, array $images): ?string
            {
                return $this->send($prompt, null, 200, $images);
            }
        };
    }
}
