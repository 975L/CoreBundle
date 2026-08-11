<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Service\LegalModelCacheTagProvider;
use PHPUnit\Framework\TestCase;

class LegalModelCacheTagProviderTest extends TestCase
{
    public function testItIsDiscoverableByTheProviderPass(): void
    {
        $this->assertInstanceOf(BlockCacheTagProviderInterface::class, new LegalModelCacheTagProvider());
    }

    // What BlockExtension::renderBlock() puts on the entry, and what LegalPlaceholderCacheListener invalidates
    public function testALegalModelBlockCarriesThePlaceholderTag(): void
    {
        $registry = new BlockCacheTagRegistry();
        $registry->addProvider(new LegalModelCacheTagProvider());

        $block = new Block()->setKind('legal_model');

        $this->assertSame([LegalModelCacheTagProvider::CACHE_TAG], $registry->getExtraTags($block));
    }

    public function testAnotherKindGetsNoExtraTag(): void
    {
        $registry = new BlockCacheTagRegistry();
        $registry->addProvider(new LegalModelCacheTagProvider());

        $this->assertSame([], $registry->getExtraTags(new Block()->setKind('article')));
    }
}
