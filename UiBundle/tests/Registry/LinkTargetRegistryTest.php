<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\LinkTargetProviderInterface;
use c975L\UiBundle\Registry\LinkTargetRegistry;
use PHPUnit\Framework\TestCase;

class LinkTargetRegistryTest extends TestCase
{
    // One list, sorted by what the editor reads, whichever bundle each entry comes from
    public function testTheTargetsOfEveryProviderAreMergedAndSortedByLabel(): void
    {
        $registry = new LinkTargetRegistry();
        $registry->addProvider($this->provider(['Shop' => 'route:shop_index']));
        $registry->addProvider($this->provider(['Home' => 'page:1', 'Services → Our offer' => 'page:12#offer-34']));

        $this->assertSame(
            ['Home' => 'page:1', 'Services → Our offer' => 'page:12#offer-34', 'Shop' => 'route:shop_index'],
            $registry->all()
        );
    }

    // A page holds a dozen link fields, and a provider walks every page of the site to list its sections: asked once per request
    public function testTheProvidersAreAskedOnce(): void
    {
        $provider = $this->createMock(LinkTargetProviderInterface::class);
        $provider->expects($this->once())->method('linkTargets')->willReturn(['Home' => 'page:1']);

        $registry = new LinkTargetRegistry();
        $registry->addProvider($provider);
        $registry->all();

        $this->assertSame(['Home' => 'page:1'], $registry->all());
    }

    /** @param array<string, string> $targets */
    private function provider(array $targets): LinkTargetProviderInterface
    {
        $provider = $this->createStub(LinkTargetProviderInterface::class);
        $provider->method('linkTargets')->willReturn($targets);

        return $provider;
    }
}
