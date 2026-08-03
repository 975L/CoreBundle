<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace <?= $namespace ?>;

use <?= $embedding_client_full_name ?>;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class <?= $class_name ?> extends TestCase
{
    private function createConfigService(array $values): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap(
            array_map(fn (string $key, mixed $value) => [$key, $value], array_keys($values), array_values($values))
        );

        return $configService;
    }

    private function enabledConfig(array $overrides = []): array
    {
        return array_merge([
            'donovan-qa-semantic-cache-enabled' => true,
            'donovan-qa-llm-api-key' => 'euria-key',
            'donovan-qa-llm-base-uri' => 'https://api.infomaniak.com/2/ai/110017/openai/v1',
            'donovan-qa-embedding-model' => 'Qwen/Qwen3-Embedding-8B',
        ], $overrides);
    }

    public function testIsEnabledRequiresExplicitEnabled(): void
    {
        $client = new <?= $embedding_client_short_name ?>(
            new MockHttpClient(),
            $this->createConfigService($this->enabledConfig(['donovan-qa-semantic-cache-enabled' => false])),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertFalse($client->isEnabled());
    }

    public function testIsEnabledRequiresAnEmbeddingModel(): void
    {
        $client = new <?= $embedding_client_short_name ?>(
            new MockHttpClient(),
            $this->createConfigService($this->enabledConfig(['donovan-qa-embedding-model' => null])),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertFalse($client->isEnabled());
    }

    public function testEmbedCallsTheEmbeddingsEndpointWithTheConfiguredModel(): void
    {
        $requestedUrl = null;
        $requestedBody = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requestedUrl, &$requestedBody) {
                $requestedUrl = $url;
                $requestedBody = json_decode($options['body'], true);

                return new MockResponse(
                    json_encode(['data' => [['embedding' => [0.1, -0.2, 0.3]]]]),
                    ['http_code' => 200]
                );
            }
        );

        $client = new <?= $embedding_client_short_name ?>(
            $httpClient,
            $this->createConfigService($this->enabledConfig()),
            $this->createStub(LoggerInterface::class),
        );

        $embedding = $client->embed('Which block for a gallery?');

        $this->assertSame('https://api.infomaniak.com/2/ai/110017/openai/v1/embeddings', $requestedUrl);
        $this->assertSame('Qwen/Qwen3-Embedding-8B', $requestedBody['model']);
        $this->assertSame([0.1, -0.2, 0.3], $embedding);
    }

    public function testEmbedReturnsNullOnTransportError(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 500])
        );

        $client = new <?= $embedding_client_short_name ?>(
            $httpClient,
            $this->createConfigService($this->enabledConfig()),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertNull($client->embed('Which block for a gallery?'));
    }
}
