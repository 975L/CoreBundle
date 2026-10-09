<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Twig;

use c975L\ConfigBundle\Management\FeedRenderer;
use c975L\ConfigBundle\Twig\FeedExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class FeedExtensionTest extends TestCase
{
    private function createExtension(array $feeds): FeedExtension
    {
        $feedRenderer = $this->createStub(FeedRenderer::class);
        $feedRenderer->method('available')->willReturn($feeds);

        return new FeedExtension($feedRenderer);
    }

    public function testGetFeedsHandsOverTheAvailableFeeds(): void
    {
        $this->assertSame(['strip' => 'Latest strips'], $this->createExtension(['strip' => 'Latest strips'])->getFeeds());
    }

    // No feed holding an entry: the layout prints no <link> at all
    public function testGetFeedsIsEmptyWithoutFeed(): void
    {
        $this->assertSame([], $this->createExtension([])->getFeeds());
    }

    public function testFunctionIsRegistered(): void
    {
        $functions = new AttributeExtension(FeedExtension::class)->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('feeds', $functions[0]->getName());
    }
}
