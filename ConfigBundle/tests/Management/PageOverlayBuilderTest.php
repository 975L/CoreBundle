<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\PageOverlayBuilder;
use c975L\ConfigBundle\Management\PageOverlayProviderInterface;
use PHPUnit\Framework\TestCase;

class PageOverlayBuilderTest extends TestCase
{
    private function createProvider(array $widgets): PageOverlayProviderInterface
    {
        $provider = $this->createStub(PageOverlayProviderInterface::class);
        $provider->method('getPageOverlays')->willReturn($widgets);

        return $provider;
    }

    public function testGetOverlaysMergesAcrossProviders(): void
    {
        $providerA = $this->createProvider([['template' => '@a/overlay.html.twig', 'context' => []]]);
        $providerB = $this->createProvider([]);
        $builder = new PageOverlayBuilder([$providerA, $providerB]);

        $this->assertSame([['template' => '@a/overlay.html.twig', 'context' => []]], $builder->getOverlays());
    }

    public function testGetOverlaysIsEmptyWhenNoProviderContributesAnything(): void
    {
        $builder = new PageOverlayBuilder([$this->createProvider([]), $this->createProvider([])]);

        $this->assertSame([], $builder->getOverlays());
    }
}
