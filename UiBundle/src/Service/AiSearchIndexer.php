<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\HealthCheck;
use c975L\UiBundle\Entity\AiSearchChunk;
use c975L\UiBundle\Repository\AiSearchChunkRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Builds the site search's index from the site itself, read as an anonymous visitor reads it: the urls every bundle already declares through its sitemap, fetched over http without a cookie, so a page reserved to members, a draft or a back-office screen can never end up in an answer. Only the urls carrying a title are read - the same curated set llms.txt is built from, which keeps a gallery's two thousand photos out of it
class AiSearchIndexer
{
    // Fetched concurrently, a batch at a time, like ContentQualityAnalyzer does
    private const int BATCH_SIZE = 10;

    // A bound on a run, whatever a bundle declares
    private const int MAX_PAGES = 1000;

    public function __construct(
        #[AutowireIterator('c975l.sitemap_provider')]
        private readonly iterable $sitemapProviders,
        private readonly HttpClientInterface $httpClient,
        private readonly AiSearchPageReader $pageReader,
        private readonly AiSearchChunkRepository $chunkRepository,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    // Reads every page and swaps the index when what they say has changed - left alone otherwise, so the answers recorded against it stay valid. A run reading no page at all (the site down, in maintenance) keeps the index it had rather than emptying it
    /** @return array{pages: int, chunks: int, changed: bool} */
    public function index(): array
    {
        $chunks = [];
        $pages = 0;
        foreach (array_chunk($this->urls(), self::BATCH_SIZE) as $batch) {
            foreach ($this->fetch($batch) as $url => $html) {
                $page = $this->pageReader->read($html, $this->defaultLocale);
                if (null === $page) {
                    continue;
                }

                ++$pages;
                foreach ($page['chunks'] as $content) {
                    $chunks[] = ['url' => $url, 'title' => $page['title'], 'content' => $content, 'locale' => $page['locale']];
                }
            }
        }

        if ([] === $chunks) {
            return ['pages' => 0, 'chunks' => 0, 'changed' => false];
        }

        $version = sha1(serialize($chunks));
        if ($version === $this->chunkRepository->currentVersion()) {
            return ['pages' => $pages, 'chunks' => \count($chunks), 'changed' => false];
        }

        $this->chunkRepository->replaceAll(array_map(
            fn (array $chunk): AiSearchChunk => new AiSearchChunk($chunk['url'], $chunk['title'], $chunk['content'], $chunk['locale'], $version),
            $chunks,
        ));

        return ['pages' => $pages, 'chunks' => \count($chunks), 'changed' => true];
    }

    // @return list<string>
    private function urls(): array
    {
        $urls = [];
        foreach ($this->sitemapProviders as $provider) {
            foreach ($provider->getUrls() as $url) {
                // Taken as mixed, like SeoFilesWriter does: the rows are other bundles' code, and one incomplete row is skipped rather than taking the whole run down
                if (\is_array($url) && \is_string($url['loc'] ?? null) && '' !== ($url['title'] ?? '')) {
                    $urls[$url['loc']] = true;
                }
            }
        }

        return \array_slice(array_keys($urls), 0, self::MAX_PAGES);
    }

    // Every request of the batch fired before any response is read. Sent as the site's own health-check probe, so the front limiter doesn't answer the site's own run with a 429
    // @param list<string> $urls
    // @return array<string, string> The html of each page answering 200 with some
    private function fetch(array $urls): array
    {
        $responses = [];
        foreach ($urls as $url) {
            $responses[$url] = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => HealthCheck::USER_AGENT],
                'timeout' => 15,
                'max_redirects' => 0,
            ]);
        }

        $pages = [];
        foreach ($responses as $url => $response) {
            try {
                if (200 === $response->getStatusCode() && str_contains($response->getHeaders(false)['content-type'][0] ?? '', 'text/html')) {
                    $pages[$url] = $response->getContent();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('AI search indexing skipped {url}: {message}', ['url' => $url, 'message' => $e->getMessage()]);
            }
        }

        return $pages;
    }
}
