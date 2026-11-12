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
use c975L\UiBundle\Contract\AiProviderInterface;
use c975L\UiBundle\Service\AbstractAiProviderClient;
use c975L\UiBundle\Service\AiUsageTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// The API a feature calls is the provider its "<prefix>-provider" names, shipped or added: the feature hands it its own entries and records what it was billed
class AiProviderConnectorTest extends TestCase
{
    public function testAnAddedProviderIsCalledWithTheFeatureEntries(): void
    {
        $provider = $this->createMock(AiProviderInterface::class);
        $provider->method('getSlug')->willReturn('acme');
        $provider->expects($this->once())->method('complete')
            ->with('Prompt', 'System', 2048, [], ['api-key' => 'key', 'base-uri' => 'https://api.example', 'model' => 'model'])
            ->willReturn(['text' => 'Answer', 'inputTokens' => 10, 'outputTokens' => 2]);

        $tracker = $this->createMock(AiUsageTracker::class);
        $tracker->expects($this->once())->method('record')->with(10, 2, 'test');

        $client = $this->createClient('acme', $tracker);
        $client->setProviders([$provider]);

        $this->assertSame('Answer', $client->ask());
    }

    // A provider nobody registered is never guessed at: the feature answers nothing
    public function testAnUnknownProviderAnswersNothing(): void
    {
        $this->assertNull($this->createClient('unknown', $this->createStub(AiUsageTracker::class))->ask());
    }

    // The container hands a feature every tagged provider, whatever its own constructor passes on
    public function testTheContainerHandsTheTaggedProviders(): void
    {
        $container = new ContainerBuilder();
        $container->register(HttpClientInterface::class, MockHttpClient::class);
        $container->register(ConfigServiceInterface::class)->setSynthetic(true);
        $container->register(AiUsageTracker::class)->setSynthetic(true);
        $container->register('logger', NullLogger::class);
        $container->setAlias(LoggerInterface::class, 'logger');
        $container->register(AcmeAiProvider::class)->addTag('c975l.ai_provider');
        $container->register(TestAiClient::class)->setAutowired(true)->setPublic(true);
        $container->compile(true);

        $calls = $container->getDefinition(TestAiClient::class)->getMethodCalls();

        $this->assertSame('setProviders', $calls[0][0]);
        $this->assertEquals([new Reference(AcmeAiProvider::class)], $calls[0][1][0]->getValues());
    }

    private function createClient(string $provider, AiUsageTracker $tracker): TestAiClient
    {
        $values = ['test-provider' => $provider, 'test-api-key' => 'key', 'test-base-uri' => 'https://api.example', 'test-model' => 'model'];
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturnCallback(static fn (string $key): mixed => $values[$key] ?? null);

        return new TestAiClient(new MockHttpClient(), $config, new NullLogger(), $tracker);
    }
}

// A feature of its own, under the "test" prefix
class TestAiClient extends AbstractAiProviderClient
{
    public function ask(): ?string
    {
        return $this->send('Prompt', 'System');
    }

    protected function configPrefix(): string
    {
        return 'test';
    }

    protected function feature(): string
    {
        return 'test';
    }
}

// A provider added by a site
class AcmeAiProvider implements AiProviderInterface
{
    public function getSlug(): string
    {
        return 'acme';
    }

    public function complete(string $prompt, ?string $system, int $maxTokens, array $images, array $settings): array
    {
        return ['text' => '', 'inputTokens' => 0, 'outputTokens' => 0];
    }
}
