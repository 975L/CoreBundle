<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use c975L\UiBundle\Service\CollectionBlockCacheTagProvider;
use PHPUnit\Framework\TestCase;

class CollectionBlockCacheTagProviderTest extends TestCase
{
    private function resolve(array $data, array $cacheTags): ?array
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('cacheTags')->willReturn($cacheTags);

        $resolvers = new CollectionBlockCacheTagProvider($sourceRegistry)->getCacheTagResolvers();

        return $resolvers['collection'](new Block()->setKind('collection')->setData($data));
    }

    public function testTheSourceOwnCacheTagsAreWhatTheBlockEntryCarries(): void
    {
        $this->assertSame(['guild_character'], $this->resolve(['source' => 'guild.characters'], ['guild_character']));
    }

    // A source declaring no tag is a source saying it cannot tell when its items change - rendered live rather than cached until something nobody wrote invalidates it
    public function testASourceDeclaringNoCacheTagVetoesTheEntry(): void
    {
        $this->assertNull($this->resolve(['source' => 'guild.characters'], []));
    }

    // A block saved before its source was picked has nothing to be cached against
    public function testABlockWithNoSourceVetoesTheEntry(): void
    {
        $this->assertNull($this->resolve(['limit' => 3], ['guild_character']));
    }

    // With a "detailPage", each item carries a link built from the page currently being rendered - one entry per block would freeze one page's links into another's html. The items themselves are still cached, keyed on the detail url they were built with (see CollectionRuntime)
    public function testABlockConfiguredWithADetailPageVetoesTheEntry(): void
    {
        $this->assertNull($this->resolve(
            ['source' => 'site.collection.projects', 'detailPage' => 'projets/fiche'],
            ['site_collection_4']
        ));
    }
}
