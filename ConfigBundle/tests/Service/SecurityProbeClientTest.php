<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\SecurityProbeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SecurityProbeClientTest extends TestCase
{
    public function testProbeReturnsTheStatusTheLowercasedHeadersAndTheBody(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('Index of /vendor/', [
                'http_code' => 200,
                'response_headers' => [
                    'Content-Type' => 'text/html',
                    'Set-Cookie' => ['PHPSESSID=abc; httponly', 'locale=fr'],
                ],
            ])
        );

        $response = (new SecurityProbeClient($httpClient))->probe('https://example.com/vendor/');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['text/html'], $response['headers']['content-type']);
        // Every value, not just the first: a response sets several cookies and only one of them is the session's
        $this->assertSame(['PHPSESSID=abc; httponly', 'locale=fr'], $response['headers']['set-cookie']);
        $this->assertSame('Index of /vendor/', $response['body']);
    }

    // Every http status is a result here - a 404 is the answer proving a path is not served
    public function testProbeDoesNotThrowOnANonSuccessStatusCode(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('Not found', ['http_code' => 404])
        );

        $this->assertSame(404, (new SecurityProbeClient($httpClient))->probe('https://example.com/.env')['status']);
    }

    // A redirect is an answer in itself: a site sending /_profiler back to its home page is not exposing it
    public function testProbeDoesNotFollowRedirects(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['Location' => 'https://example.com/'],
            ])
        );

        $this->assertSame(302, (new SecurityProbeClient($httpClient))->probe('https://example.com/_profiler')['status']);
    }

    public function testProbeTruncatesTheBody(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(str_repeat('x', 10000), ['http_code' => 200])
        );

        $this->assertSame(2048, \strlen((new SecurityProbeClient($httpClient))->probe('https://example.com/')['body']));
    }

    public function testProbePropagatesTransportExceptions(): void
    {
        $this->expectException(TransportException::class);

        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['error' => 'Connection refused'])
        );

        (new SecurityProbeClient($httpClient))->probe('https://example.com/');
    }
}
