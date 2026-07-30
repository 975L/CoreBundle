<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Registry\BlockEditUrlRegistry;
use PHPUnit\Framework\TestCase;

class BlockEditUrlRegistryTest extends TestCase
{
    public function testGetEditUrlsReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new BlockEditUrlRegistry();

        $this->assertSame([], $registry->getEditUrls([]));
    }

    public function testGetEditUrlsReturnsSingleProviderResult(): void
    {
        $provider = $this->createStub(BlockEditUrlProviderInterface::class);
        $provider->method('getEditUrls')->willReturn([1 => '/management?entityId=1']);

        $registry = new BlockEditUrlRegistry();
        $registry->addProvider($provider);

        $this->assertSame([1 => '/management?entityId=1'], $registry->getEditUrls([]));
    }

    // Each block is owned by at most one provider - the first provider to resolve a block id wins
    public function testGetEditUrlsKeepsFirstProviderResultForSameBlockId(): void
    {
        $providerA = $this->createStub(BlockEditUrlProviderInterface::class);
        $providerA->method('getEditUrls')->willReturn([1 => '/from-a']);

        $providerB = $this->createStub(BlockEditUrlProviderInterface::class);
        $providerB->method('getEditUrls')->willReturn([1 => '/from-b']);

        $registry = new BlockEditUrlRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame([1 => '/from-a'], $registry->getEditUrls([]));
    }

    public function testGetEditUrlsKeepsEntriesForDifferentBlockIdsSeparate(): void
    {
        $provider = $this->createStub(BlockEditUrlProviderInterface::class);
        $provider->method('getEditUrls')->willReturn([
            1 => '/a',
            2 => '/b',
        ]);

        $registry = new BlockEditUrlRegistry();
        $registry->addProvider($provider);

        $urls = $registry->getEditUrls([]);
        $this->assertArrayHasKey(1, $urls);
        $this->assertArrayHasKey(2, $urls);
    }
}
