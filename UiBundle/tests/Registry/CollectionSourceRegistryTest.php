<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use PHPUnit\Framework\TestCase;

class CollectionSourceRegistryTest extends TestCase
{
    private function createProvider(array $sources): CollectionSourceProviderInterface
    {
        $provider = $this->createStub(CollectionSourceProviderInterface::class);
        $provider->method('getSources')->willReturn($sources);

        return $provider;
    }

    public function testHasReturnsFalseWhenNoProviderRegisteredTheSource(): void
    {
        $registry = new CollectionSourceRegistry();

        $this->assertFalse($registry->has('site.collection.projects'));
    }

    public function testItemsReturnsEmptyArrayWhenSourceIsUnknown(): void
    {
        $registry = new CollectionSourceRegistry();

        $this->assertSame([], $registry->items('site.collection.projects', null));
    }

    public function testHasAndItemsReflectRegisteredSource(): void
    {
        $items = [new CollectionItem('Project A')];
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'site.collection.projects' => [
                'label' => 'Projects',
                'items' => fn (?int $limit) => $items,
            ],
        ]));

        $this->assertTrue($registry->has('site.collection.projects'));
        $this->assertSame($items, $registry->items('site.collection.projects', 6));
    }

    public function testChoicesMapsTranslatedLabelToSourceKey(): void
    {
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'site.collection.projects' => ['label' => 'Projects', 'items' => fn () => []],
            'book.all' => ['label' => 'Books', 'items' => fn () => []],
        ]));

        $this->assertSame(
            ['Projects' => 'site.collection.projects', 'Books' => 'book.all'],
            $registry->choices()
        );
    }

    public function testChoicesDisambiguatesSourcesSharingTheSameLabel(): void
    {
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'site.collection.projects' => ['label' => 'Projects', 'items' => fn () => []],
            'book.projects' => ['label' => 'Projects', 'items' => fn () => []],
        ]));

        $this->assertSame(
            [
                'Projects' => 'site.collection.projects',
                'Projects (book.projects)' => 'book.projects',
            ],
            $registry->choices()
        );
    }

    // Sources are merged across providers, so a satellite bundle's own sources coexist with others'
    public function testSourcesFromSeveralProvidersAreMerged(): void
    {
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'site.collection.projects' => ['label' => 'Projects', 'items' => fn () => []],
        ]));
        $registry->addProvider($this->createProvider([
            'book.all' => ['label' => 'Books', 'items' => fn () => []],
        ]));

        $this->assertTrue($registry->has('site.collection.projects'));
        $this->assertTrue($registry->has('book.all'));
    }

    // The total is what a source can answer without building a single item, and a source declaring none simply says nothing about how many it holds
    public function testCountIsTheSourcesOwnTotalOrNothing(): void
    {
        $built = false;
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'book.all' => [
                'label' => 'Books',
                'count' => fn (): int => 128,
                'items' => function () use (&$built) {
                    $built = true;

                    return [];
                },
            ],
            'site.collection.projects' => ['label' => 'Projects', 'items' => fn () => []],
        ]));

        $this->assertSame(128, $registry->count('book.all'));
        $this->assertNull($registry->count('site.collection.projects'));
        $this->assertNull($registry->count('unknown.source'));
        $this->assertFalse($built, 'The total must be asked for without building the source\'s items.');
    }

    public function testCacheTagsReturnsWhatTheSourceDeclared(): void
    {
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'book.all' => ['label' => 'Books', 'items' => fn () => [], 'cacheTags' => ['book_books']],
        ]));

        $this->assertSame(['book_books'], $registry->cacheTags('book.all'));
    }

    // No tag is a source saying it cannot tell when its items change, which is what makes them rendered live rather than cached forever
    public function testCacheTagsIsEmptyForASourceDeclaringNoneAndForAnUnknownOne(): void
    {
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'site.collection.projects' => ['label' => 'Projects', 'items' => fn () => []],
        ]));

        $this->assertSame([], $registry->cacheTags('site.collection.projects'));
        $this->assertSame([], $registry->cacheTags('book.all'));
    }

    // A source whose items are not what the built-in card draws names the template that draws them; declaring none keeps that card, which is every source that has ever existed
    public function testItemTemplateIsTheSourcesOwnOrNothing(): void
    {
        $registry = new CollectionSourceRegistry();
        $registry->addProvider($this->createProvider([
            'guild.albums' => ['label' => 'Albums', 'items' => fn () => [], 'itemTemplate' => 'components/Album/AlbumCard.html.twig'],
            'site.collection.projects' => ['label' => 'Projects', 'items' => fn () => []],
        ]));

        $this->assertSame('components/Album/AlbumCard.html.twig', $registry->itemTemplate('guild.albums'));
        $this->assertNull($registry->itemTemplate('site.collection.projects'));
        $this->assertNull($registry->itemTemplate('unknown.source'));
    }

    public function testItemsPassesTheLimitToTheSourcesCallable(): void
    {
        $registry = new CollectionSourceRegistry();
        $received = null;
        $registry->addProvider($this->createProvider([
            'site.collection.projects' => [
                'label' => 'Projects',
                'items' => function (?int $limit) use (&$received) {
                    $received = $limit;

                    return [];
                },
            ],
        ]));

        $registry->items('site.collection.projects', 6);

        $this->assertSame(6, $received);
    }
}
