<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\InternalLinkLocalizerInterface;
use c975L\UiBundle\Registry\InternalLinkLocalizerRegistry;
use PHPUnit\Framework\TestCase;

class InternalLinkLocalizerRegistryTest extends TestCase
{
    // A site declaring one language registers no localizer at all, and every link is left exactly as it was stored
    public function testAValueIsGivenBackUntouchedWhenNobodyLocalizesAnything(): void
    {
        $this->assertSame('/pages/nos-ateliers', new InternalLinkLocalizerRegistry()->localize('/pages/nos-ateliers'));
    }

    // Chained rather than first-wins: a rich text holds links of several bundles at once, and each one only rewrites its own
    public function testEveryLocalizerGetsToRewriteItsOwnLinks(): void
    {
        $registry = new InternalLinkLocalizerRegistry();
        $registry->addProvider($this->localizer('/pages/', '/en/pages/'));
        $registry->addProvider($this->localizer('/shop/', '/en/shop/'));

        $this->assertSame(
            'read <a href="/en/pages/a">this</a> then buy <a href="/en/shop/b">that</a>',
            $registry->localize('read <a href="/pages/a">this</a> then buy <a href="/shop/b">that</a>'),
        );
    }

    private function localizer(string $from, string $to): InternalLinkLocalizerInterface
    {
        return new readonly class ($from, $to) implements InternalLinkLocalizerInterface {
            public function __construct(private string $from, private string $to)
            {
            }

            public function localize(string $value): string
            {
                return str_replace($this->from, $this->to, $value);
            }
        };
    }
}
