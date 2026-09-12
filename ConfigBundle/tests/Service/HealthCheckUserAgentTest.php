<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use PHPUnit\Framework\TestCase;

// Holds HealthCheck::USER_AGENT's own docblock to its word - "sent by every client that probes a site on the console's behalf" was written once and then contradicted by five clients that set no agent at all, which RateLimitListener then counted against the very site being measured.
//
// Swept from the source rather than exercised through each client, so a probe added tomorrow is covered the day it is written instead of the day someone remembers to test it. The polarity is deliberate: every caller is held to the contract unless it is named below, an unknown new client failing the test rather than slipping past it
class HealthCheckUserAgentTest extends TestCase
{
    // The two callers that talk to a third party rather than probing a site: announcing ourselves as a health checker to an OAuth provider or to a crawler-list host would be a lie, and neither answers to our own rate limiter
    private const array NOT_PROBES = [
        'AiCrawlerListClient.php',
        'OAuthLoginClient.php',
    ];

    public function testEveryProbeSendsTheHealthCheckUserAgent(): void
    {
        $offenders = [];
        foreach ($this->probeSources() as $file => $source) {
            foreach ($this->requestCalls($source) as $line => $call) {
                if (!str_contains($call, 'HealthCheck::USER_AGENT')) {
                    $offenders[] = $file . ':' . $line;
                }
            }
        }

        $this->assertSame([], $offenders, 'These probe requests carry no HealthCheck::USER_AGENT, so RateLimitListener counts them as ordinary traffic: ' . implode(', ', $offenders));
    }

    // Guards the exemption list itself: a name left behind after a rename would silently stop sweeping a real probe
    public function testExemptedClientsStillExist(): void
    {
        foreach (self::NOT_PROBES as $file) {
            $this->assertFileExists($this->serviceDir() . '/' . $file);
        }
    }

    // Every service that issues an HTTP request, minus the ones exempted above
    private function probeSources(): array
    {
        $sources = [];
        foreach (glob($this->serviceDir() . '/*.php') as $path) {
            $name = basename($path);
            $source = file_get_contents($path);
            if (!\in_array($name, self::NOT_PROBES, true) && str_contains($source, 'httpClient->request(')) {
                $sources[$name] = $source;
            }
        }

        return $sources;
    }

    // Each request() call as written, from the arrow to the closing parenthesis of its statement, keyed by line number - a call split over several lines is read whole rather than judged on its first line
    private function requestCalls(string $source): array
    {
        $calls = [];
        $offset = 0;
        while (false !== $start = strpos($source, 'httpClient->request(', $offset)) {
            $end = strpos($source, ');', $start);
            $end = false === $end ? \strlen($source) : $end;
            $calls[substr_count($source, "\n", 0, $start) + 1] = substr($source, $start, $end - $start);
            $offset = $end;
        }

        return $calls;
    }

    private function serviceDir(): string
    {
        return \dirname(__DIR__, 2) . '/src/Service';
    }
}
