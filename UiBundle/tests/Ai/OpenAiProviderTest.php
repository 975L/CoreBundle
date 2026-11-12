<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Ai;

use c975L\UiBundle\Ai\EuriaProvider;
use c975L\UiBundle\Ai\OpenAiProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// A reasoning model's hidden reasoning counts against the ceiling: it is asked to think as little as its family allows, so a short ceiling still leaves room for the answer
class OpenAiProviderTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function models(): iterable
    {
        yield 'gpt-5' => ['gpt-5', 'minimal'];
        yield 'gpt-5-mini' => ['gpt-5-mini', 'minimal'];
        yield 'gpt-5.1' => ['gpt-5.1', 'none'];
        yield 'gpt-5.2-mini' => ['gpt-5.2-mini', 'none'];
        yield 'o-series' => ['o4-mini', 'low'];
        yield 'gpt-5 chat, no reasoning' => ['gpt-5-chat-latest', null];
        yield 'gpt-4o, no reasoning' => ['gpt-4o-mini', null];
    }

    #[DataProvider('models')]
    public function testEachFamilyIsSentTheLeastReasoningItTakes(string $model, ?string $effort): void
    {
        $this->assertSame($effort, $this->sentBody(new OpenAiProvider($this->httpClient($body)), $model, $body)['reasoning_effort'] ?? null);
    }

    // Euria's models do not reason, whatever their name
    public function testEuriaIsSentNoReasoningEffort(): void
    {
        $this->assertArrayNotHasKey('reasoning_effort', $this->sentBody(new EuriaProvider($this->httpClient($body)), 'o4-mini', $body));
    }

    // The body the provider posted for that model
    private function sentBody(OpenAiProvider $provider, string $model, mixed &$body): array
    {
        $provider->complete('question', null, 400, [], ['base-uri' => 'https://api.example/v1', 'api-key' => 'key', 'model' => $model]);

        return $body;
    }

    // Answers every call and keeps the json body it was sent
    private function httpClient(mixed &$body): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = json_decode($options['body'], true);

            return new MockResponse((string) json_encode(['choices' => [['message' => ['content' => 'answer']]]]));
        });
    }
}
