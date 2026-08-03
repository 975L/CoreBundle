<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace <?= $namespace ?>;

use <?= $llm_client_full_name ?>;
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

    public function testIsEnabledRequiresAnApiKey(): void
    {
        $client = new <?= $llm_client_short_name ?>(
            new MockHttpClient(),
            $this->createConfigService(['donovan-qa-llm-api-key' => null]),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertFalse($client->isEnabled());
    }

    public function testIsEnabledRequiresExplicitEnabled(): void
    {
        $client = new <?= $llm_client_short_name ?>(
            new MockHttpClient(),
            $this->createConfigService([
                'donovan-qa-llm-enabled' => false,
                'donovan-qa-llm-provider' => 'anthropic',
                'donovan-qa-llm-api-key' => 'anthropic-key',
            ]),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertFalse($client->isEnabled());
    }

    public function testIsEnabledRequiresAModelAndBaseUriForEuria(): void
    {
        $client = new <?= $llm_client_short_name ?>(
            new MockHttpClient(),
            $this->createConfigService([
                'donovan-qa-llm-enabled' => true,
                'donovan-qa-llm-provider' => 'euria',
                'donovan-qa-llm-api-key' => 'euria-key',
                'donovan-qa-llm-model' => null,
                'donovan-qa-llm-base-uri' => null,
            ]),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertFalse($client->isEnabled());
    }

    public function testAskReturnsNullWhenDisabled(): void
    {
        $client = new <?= $llm_client_short_name ?>(
            new MockHttpClient(),
            $this->createConfigService(['donovan-qa-llm-api-key' => null]),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertNull($client->ask('Which block for a gallery?', 'context'));
    }

    public function testAskParsesTheTrailingSourcesLineFromAnthropic(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(
                json_encode([
                    'content' => [['text' => "Use the collection block.\nSOURCES: collection"]],
                ]),
                ['http_code' => 200]
            )
        );

        $client = new <?= $llm_client_short_name ?>(
            $httpClient,
            $this->createConfigService([
                'donovan-qa-llm-enabled' => true,
                'donovan-qa-llm-provider' => 'anthropic',
                'donovan-qa-llm-api-key' => 'anthropic-key',
            ]),
            $this->createStub(LoggerInterface::class),
        );

        $result = $client->ask('Which block for a gallery?', 'context');

        $this->assertSame('Use the collection block.', $result['answer']);
        $this->assertSame(['collection'], $result['sourceKinds']);
    }
}
