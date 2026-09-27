<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Contract\BlockPageUrlProviderInterface;
use c975L\UiBundle\Twig\BlockPageUrlExtension;
use PHPUnit\Framework\TestCase;

class BlockPageUrlExtensionTest extends TestCase
{
    // The first provider knowing a page wins, one answering null passing the question on
    public function testTheFirstAnswerWins(): void
    {
        $extension = new BlockPageUrlExtension([$this->provider(null), $this->provider('/tarifs#packs-27'), $this->provider('/other')]);

        $this->assertSame('/tarifs#packs-27', $extension->getBlockPageUrl('purchasecredits_packs'));
    }

    // No provider, or none knowing a page: null, the caller linking nowhere rather than to a guess
    public function testNullWhenNoPageShowsTheKind(): void
    {
        $this->assertNull(new BlockPageUrlExtension([])->getBlockPageUrl('purchasecredits_packs'));
        $this->assertNull(new BlockPageUrlExtension([$this->provider(null)])->getBlockPageUrl('purchasecredits_packs'));
    }

    // A provider answering that url for any kind
    private function provider(?string $url): BlockPageUrlProviderInterface
    {
        $provider = $this->createStub(BlockPageUrlProviderInterface::class);
        $provider->method('getBlockPageUrl')->willReturn($url);

        return $provider;
    }
}
