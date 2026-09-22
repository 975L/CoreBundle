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
use c975L\UiBundle\Entity\AiUsage;
use c975L\UiBundle\Service\AiSiteSearchClient;
use c975L\UiBundle\Service\AiUsageTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// Its own config entries and its own spend row: the site search never rides on the rephrase's key nor raises its alert
class AiSiteSearchClientTest extends TestCase
{
    public function testCallsItsOwnProviderWithTheRulesAsSystemPromptAndRecordsItsOwnSpend(): void
    {
        $sent = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['url' => $url, 'body' => json_decode($options['body'], true)];

            return new MockResponse(json_encode([
                'choices' => [['message' => ['content' => '{"answer": "Oui", "sources": [1]}']]],
                'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 20],
            ]));
        });

        $tracker = $this->createMock(AiUsageTracker::class);
        $tracker->expects($this->once())->method('record')->with(900, 20, AiUsage::FEATURE_SITE_SEARCH);

        $reply = new AiSiteSearchClient($httpClient, $this->config(), $this->createStub(LoggerInterface::class), $tracker)
            ->answer('Ouvert le samedi ?', [['url' => 'https://site.example/horaires', 'title' => 'Horaires', 'content' => 'Ouvert du lundi au samedi.']], 'Garage', 'fr');

        $this->assertSame('{"answer": "Oui", "sources": [1]}', $reply);
        $this->assertSame('https://api.example/v1/chat/completions', $sent['url']);
        $this->assertSame('site-model', $sent['body']['model']);
        $this->assertArrayNotHasKey('max_tokens', $sent['body']);
        $this->assertSame('system', $sent['body']['messages'][0]['role']);
        $this->assertStringContainsString('"Garage"', $sent['body']['messages'][0]['content']);
        $this->assertStringContainsString("[1] Horaires\nOuvert du lundi au samedi.", $sent['body']['messages'][1]['content']);
        $this->assertStringEndsWith('Question: Ouvert le samedi ?', $sent['body']['messages'][1]['content']);
    }

    public function testAFailureIsRecordedUnderItsOwnFeature(): void
    {
        $tracker = $this->createMock(AiUsageTracker::class);
        $tracker->expects($this->once())->method('recordFailure')->with($this->anything(), AiUsage::FEATURE_SITE_SEARCH);

        $client = new AiSiteSearchClient(new MockHttpClient(new MockResponse('', ['http_code' => 401])), $this->config(), $this->createStub(LoggerInterface::class), $tracker);

        $this->assertNull($client->answer('Question ?', [], 'Garage', 'fr'));
    }

    public function testStaysOffWithoutItsOwnModel(): void
    {
        $client = new AiSiteSearchClient(new MockHttpClient(), $this->config(['ui-ai-assistant-site-model' => null]), $this->createStub(LoggerInterface::class), $this->createStub(AiUsageTracker::class));

        $this->assertFalse($client->isEnabled());
    }

    private function config(array $overrides = []): ConfigServiceInterface
    {
        $values = $overrides + [
            'ui-ai-assistant-site-provider' => 'euria',
            'ui-ai-assistant-site-api-key' => 'site-key',
            'ui-ai-assistant-site-base-uri' => 'https://api.example/v1',
            'ui-ai-assistant-site-model' => 'site-model',
        ];

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(fn (string $slug): mixed => $values[$slug] ?? null);

        return $configService;
    }
}
