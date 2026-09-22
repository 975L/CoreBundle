<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Service\HealthCheck;
use c975L\UiBundle\Entity\AiSearchChunk;
use c975L\UiBundle\Repository\AiSearchChunkRepository;
use c975L\UiBundle\Service\AiSearchIndexer;
use c975L\UiBundle\Service\AiSearchPageReader;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// The index holds what an anonymous visitor reads on the urls the bundles declare with a title, and is only rewritten when that changed
#[AllowMockObjectsWithoutExpectations]
class AiSearchIndexerTest extends TestCase
{
    public function testIndexesTheTitledUrlsReadAsTheSiteProbe(): void
    {
        $requested = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requested): MockResponse {
            $requested[] = $url;
            $this->assertContains('User-Agent: ' . HealthCheck::USER_AGENT, $options['headers']);

            return new MockResponse('<html lang="fr"><body><main><p>Page ' . $url . '</p></main></body></html>', ['response_headers' => ['content-type' => 'text/html; charset=UTF-8']]);
        });

        $repository = $this->createMock(AiSearchChunkRepository::class);
        $repository->method('currentVersion')->willReturn(null);
        $repository->expects($this->once())->method('replaceAll')->with($this->callback(
            fn (array $chunks): bool => 1 === \count($chunks) && $chunks[0] instanceof AiSearchChunk && 'https://site.example/a' === $chunks[0]->getUrl(),
        ));

        $result = $this->indexer($httpClient, $repository, [
            ['loc' => 'https://site.example/a', 'title' => 'A'],
            // No title: left out, like llms.txt leaves it out
            ['loc' => 'https://site.example/photo/1'],
        ])->index();

        $this->assertSame(['https://site.example/a'], $requested);
        $this->assertSame(['pages' => 1, 'chunks' => 1, 'changed' => true], $result);
    }

    // The answers recorded against the index stay valid as long as the pages say the same
    public function testAnUnchangedSiteLeavesTheIndexAlone(): void
    {
        $html = '<html lang="fr"><body><main><p>Même texte</p></main></body></html>';
        $chunks = [['url' => 'https://site.example/a', 'title' => 'Même texte', 'content' => 'Même texte', 'locale' => 'fr']];

        $repository = $this->createMock(AiSearchChunkRepository::class);
        $repository->method('currentVersion')->willReturn(sha1(serialize($chunks)));
        $repository->expects($this->never())->method('replaceAll');

        $result = $this->indexer(new MockHttpClient(new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']])), $repository, [['loc' => 'https://site.example/a', 'title' => 'A']])->index();

        $this->assertFalse($result['changed']);
    }

    // A site down or in maintenance keeps the index it had rather than emptying it
    public function testARunReadingNoPageKeepsTheIndex(): void
    {
        $repository = $this->createMock(AiSearchChunkRepository::class);
        $repository->expects($this->never())->method('replaceAll');

        $result = $this->indexer(new MockHttpClient(new MockResponse('', ['http_code' => 503])), $repository, [['loc' => 'https://site.example/a', 'title' => 'A']])->index();

        $this->assertSame(['pages' => 0, 'chunks' => 0, 'changed' => false], $result);
    }

    private function indexer(MockHttpClient $httpClient, AiSearchChunkRepository $repository, array $urls): AiSearchIndexer
    {
        $provider = $this->createStub(SitemapProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);

        return new AiSearchIndexer([$provider], $httpClient, new AiSearchPageReader(), $repository, $this->createStub(LoggerInterface::class), 'fr');
    }
}
