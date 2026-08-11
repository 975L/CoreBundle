<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\AiCrawlerListClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AiCrawlerListClientTest extends TestCase
{
    private const string SOURCE = 'https://example.com/robots.json';

    private function createClient(string $body, int $statusCode = 200): AiCrawlerListClient
    {
        return new AiCrawlerListClient(new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode])));
    }

    // The list is a json object keyed by user agent, everything else in it being prose this bundle has no use for
    public function testFetchReturnsTheUserAgentsTheListDeclares(): void
    {
        $client = $this->createClient('{"GPTBot": {"operator": "OpenAI"}, "CCBot": {"operator": "Common Crawl"}}');

        $this->assertSame(['GPTBot', 'CCBot'], $client->fetch(self::SOURCE));
    }

    // An html error page decodes to nothing: taking that for a list that lost all its entries would report every crawler this site blocks as unknown upstream
    public function testFetchThrowsOnAResponseThatIsNotAJsonObject(): void
    {
        $client = $this->createClient('<html>Not Found</html>');

        $this->expectException(\RuntimeException::class);

        $client->fetch(self::SOURCE);
    }

    public function testFetchThrowsOnAnEmptyList(): void
    {
        $client = $this->createClient('{}');

        $this->expectException(\RuntimeException::class);

        $client->fetch(self::SOURCE);
    }

    // A json list decodes fine but is keyed by numbers: read as user agents it leaves nothing to compare, and the monthly check would report the site as up to date for good
    public function testFetchThrowsOnAJsonListRatherThanAnObject(): void
    {
        $client = $this->createClient('["GPTBot", "CCBot"]');

        $this->expectException(\RuntimeException::class);

        $client->fetch(self::SOURCE);
    }

    // A moved list answering 404 must be reported, the body of such a response being no list either
    public function testFetchThrowsOnAnErrorStatus(): void
    {
        $client = $this->createClient('{"GPTBot": {}}', 404);

        $this->expectException(\RuntimeException::class);

        $client->fetch(self::SOURCE);
    }
}
