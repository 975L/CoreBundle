<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\AiSearchCardProviderInterface;
use c975L\UiBundle\Registry\AiSearchCardRegistry;
use PHPUnit\Framework\TestCase;

class AiSearchCardRegistryTest extends TestCase
{
    // The first provider drawing a url keeps it, the next one being asked only for the urls left, and the cards come back in the order of the sources
    public function testTheFirstCardForEachUrlIsKeptInTheOrderOfTheUrls(): void
    {
        $asked = [];
        $registry = new AiSearchCardRegistry();
        $registry->addProvider($this->provider(['https://site.example/b' => 'first-b'], $asked));
        $registry->addProvider($this->provider(['https://site.example/a' => 'second-a', 'https://site.example/b' => 'second-b'], $asked));

        $cards = $registry->render(['https://site.example/a', 'https://site.example/b', 'https://site.example/c']);

        $this->assertSame(['https://site.example/a' => 'second-a', 'https://site.example/b' => 'first-b'], $cards);
        $this->assertSame(['https://site.example/a', 'https://site.example/c'], $asked[1]);
    }

    public function testNoProviderDrawsNoCard(): void
    {
        $this->assertSame([], new AiSearchCardRegistry()->render(['https://site.example/a']));
    }

    private function provider(array $cards, array &$asked): AiSearchCardProviderInterface
    {
        $provider = $this->createStub(AiSearchCardProviderInterface::class);
        $provider->method('renderCards')->willReturnCallback(function (array $urls) use ($cards, &$asked): array {
            $asked[] = $urls;

            return array_intersect_key($cards, array_flip($urls));
        });

        return $provider;
    }
}
