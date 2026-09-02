<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\AiAssistantClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AiAssistantClientTest extends TestCase
{
    private function createConfigService(array $values): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap(
            array_map(fn (string $key, mixed $value) => [$key, $value], array_keys($values), array_values($values))
        );

        return $configService;
    }

    private function createGuidedProjectBuilder(array $projectsBySlug): GuidedProjectBuilder
    {
        $guidedProjectBuilder = $this->createStub(GuidedProjectBuilder::class);
        $guidedProjectBuilder->method('getProject')->willReturnCallback(fn (string $slug) => $projectsBySlug[$slug] ?? null);

        return $guidedProjectBuilder;
    }

    public function testReturnsNullWhenDisabled(): void
    {
        $client = new AiAssistantClient(
            new MockHttpClient(),
            $this->createConfigService(['ui-ai-assistant-dashboard-enabled' => false]),
            $this->createStub(GuidedProjectBuilder::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertNull($client->ask('Which block for a gallery?'));
    }

    public function testReturnsNullWhenEndpointOrTokenMissing(): void
    {
        $client = new AiAssistantClient(
            new MockHttpClient(),
            $this->createConfigService([
                'ui-ai-assistant-dashboard-enabled' => true,
                'ui-ai-assistant-dashboard-endpoint' => null,
                'ui-ai-assistant-dashboard-token' => 'some-token',
            ]),
            $this->createStub(GuidedProjectBuilder::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertNull($client->ask('Which block for a gallery?'));
    }

    public function testReturnsAnswerAndSourcesFromConfiguredEndpoint(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(
                json_encode([
                    'answer' => 'Use the collection block.',
                    'sources' => [['label' => 'Collection', 'url' => 'https://example.test/blocks#block-collection']],
                ]),
                ['http_code' => 200]
            )
        );

        $client = new AiAssistantClient(
            $httpClient,
            $this->createConfigService([
                'ui-ai-assistant-dashboard-enabled' => true,
                'ui-ai-assistant-dashboard-endpoint' => 'https://example.test/ai-assistant',
                'ui-ai-assistant-dashboard-token' => 'some-token',
            ]),
            $this->createStub(GuidedProjectBuilder::class),
            $this->createStub(LoggerInterface::class),
        );

        $result = $client->ask('Which block for a gallery?');

        $this->assertSame('Use the collection block.', $result['answer']);
        $this->assertSame(
            [['label' => 'Collection', 'url' => 'https://example.test/blocks#block-collection']],
            $result['sources'],
        );
    }

    // A backend with no citations omits "sources", defaulting to [] rather than a null-check everywhere
    public function testDefaultsSourcesToEmptyArrayWhenBackendOmitsThem(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(
                json_encode(['answer' => 'Use the collection block.']),
                ['http_code' => 200]
            )
        );

        $client = new AiAssistantClient(
            $httpClient,
            $this->createConfigService([
                'ui-ai-assistant-dashboard-enabled' => true,
                'ui-ai-assistant-dashboard-endpoint' => 'https://example.test/ai-assistant',
                'ui-ai-assistant-dashboard-token' => 'some-token',
            ]),
            $this->createStub(GuidedProjectBuilder::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertSame([], $client->ask('Which block for a gallery?')['sources']);
    }

    // The backend answers every site it serves at once, so a parcours it cites is resolved here or dropped
    public function testResolvesAGuidedProjectSourceAgainstThisSite(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(
                json_encode([
                    'answer' => 'Follow the media library tour.',
                    'sources' => [
                        ['label' => 'Media library', 'url' => '', 'project' => 'ui-media'],
                        ['label' => 'Gone with its bundle', 'url' => '', 'project' => 'shop-product'],
                    ],
                ]),
                ['http_code' => 200]
            )
        );

        $client = new AiAssistantClient(
            $httpClient,
            $this->createConfigService([
                'ui-ai-assistant-dashboard-enabled' => true,
                'ui-ai-assistant-dashboard-endpoint' => 'https://example.test/ai-assistant',
                'ui-ai-assistant-dashboard-token' => 'some-token',
            ]),
            $this->createGuidedProjectBuilder(['ui-media' => ['slug' => 'ui-media', 'label' => 'Bibliothèque de médias', 'description' => '', 'steps' => []]]),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertSame(
            [['label' => 'Bibliothèque de médias', 'url' => '', 'project' => 'ui-media']],
            $client->ask('How do I add an image?')['sources'],
        );
    }

    public function testReturnsNullOnTransportError(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 500])
        );

        $client = new AiAssistantClient(
            $httpClient,
            $this->createConfigService([
                'ui-ai-assistant-dashboard-enabled' => true,
                'ui-ai-assistant-dashboard-endpoint' => 'https://example.test/ai-assistant',
                'ui-ai-assistant-dashboard-token' => 'some-token',
            ]),
            $this->createStub(GuidedProjectBuilder::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertNull($client->ask('Which block for a gallery?'));
    }
}
