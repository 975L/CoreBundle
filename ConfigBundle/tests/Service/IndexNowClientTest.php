<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\IndexNowClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class IndexNowClientTest extends TestCase
{
    private const array URLS = ['https://example.com/', 'https://example.com/page'];

    // The body is what the protocol asks for: host, key, where the key file is, and the urls
    public function testSubmitPostsTheBatchToTheSharedEndpoint(): void
    {
        $response = new MockResponse('', ['http_code' => 200]);
        $client = new IndexNowClient(new MockHttpClient($response));

        $client->submit('example.com', 'abc123', 'https://example.com/indexnow-key.txt', self::URLS);

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://api.indexnow.org/indexnow', $response->getRequestUrl());
        $this->assertSame(
            ['host' => 'example.com', 'key' => 'abc123', 'keyLocation' => 'https://example.com/indexnow-key.txt', 'urlList' => self::URLS],
            json_decode($response->getRequestOptions()['body'], true)
        );
    }

    // 202 is what a first submission gets while the engine fetches the key file, and is no failure
    public function testSubmitAcceptsAPendingKeyValidation(): void
    {
        $client = new IndexNowClient(new MockHttpClient(new MockResponse('', ['http_code' => 202])));

        $client->submit('example.com', 'abc123', 'https://example.com/indexnow-key.txt', self::URLS);

        $this->addToAssertionCount(1);
    }

    // A refused key or batch must be reported, or the urls would be taken for submitted and never go again
    public function testSubmitThrowsOnARefusal(): void
    {
        $client = new IndexNowClient(new MockHttpClient(new MockResponse('', ['http_code' => 403])));

        $this->expectException(\RuntimeException::class);

        $client->submit('example.com', 'abc123', 'https://example.com/indexnow-key.txt', self::URLS);
    }

    public function testSubmitThrowsWhenTheEndpointCannotBeReached(): void
    {
        $client = new IndexNowClient(new MockHttpClient(static fn (): never => throw new TransportException('Could not resolve host')));

        $this->expectException(\RuntimeException::class);

        $client->submit('example.com', 'abc123', 'https://example.com/indexnow-key.txt', self::URLS);
    }
}
