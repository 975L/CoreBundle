<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use c975L\ConfigBundle\Service\UrlMetadataResolver;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UrlMetadataResolverTest extends TestCase
{
    private function createResolver(?string $currentPath, UrlMetadataRepository $repository, ?TagAwareCacheInterface $cache = null): UrlMetadataResolver
    {
        $requestStack = new RequestStack();
        if (null !== $currentPath) {
            $requestStack->push(Request::create($currentPath));
        }

        return new UrlMetadataResolver($requestStack, $repository, $cache ?? new TagAwareAdapter(new ArrayAdapter()));
    }

    private function createRepository(array $rows): UrlMetadataRepository
    {
        $repository = $this->createStub(UrlMetadataRepository::class);
        $repository->method('findIdsIndexedByPath')->willReturn(array_combine(array_keys($rows), range(1, \count($rows))));
        $repository->method('find')->willReturnCallback(fn (int $id) => array_values($rows)[$id - 1] ?? null);

        return $repository;
    }

    private function createRow(string $path): UrlMetadata
    {
        return new UrlMetadata()->setPath($path)->setTitle('Animaux');
    }

    public function testItFindsTheRowOfThePageBeingRendered(): void
    {
        $resolver = $this->createResolver('/animaux', $this->createRepository(['/animaux' => $this->createRow('/animaux')]));

        $this->assertSame('Animaux', $resolver->forCurrentRequest()?->getTitle());
    }

    // A request carrying the trailing slash lands on the same row: both forms answer the same page, and only one of them was written down
    public function testAPathIsNormalisedBeforeBeingLookedUp(): void
    {
        $repository = $this->createRepository(['/animaux' => $this->createRow('/animaux')]);

        foreach (['/animaux', '/animaux/', 'animaux'] as $path) {
            $this->assertNotNull($this->createResolver(null, $repository)->forPath($path), $path);
        }
    }

    // The normal state of a site whose listings have not been described yet - the layouts then emit no more than they did before
    public function testAnUrlWithNoRowResolvesToNull(): void
    {
        $resolver = $this->createResolver('/inconnu', $this->createRepository(['/animaux' => $this->createRow('/animaux')]));

        $this->assertNull($resolver->forCurrentRequest());
    }

    // The layouts are shared with the emails, rendered from a console command where no request exists at all
    public function testThereIsNothingToResolveOutsideAnHttpRequest(): void
    {
        $resolver = $this->createResolver(null, $this->createRepository(['/animaux' => $this->createRow('/animaux')]));

        $this->assertNull($resolver->forCurrentRequest());
    }

    // The map is read once for every request sharing the pool, and an url without a row never reaches the database
    public function testTheMapIsReadOnceAcrossRequestsAndAMissCostsNoQuery(): void
    {
        $repository = $this->createMock(UrlMetadataRepository::class);
        $repository->expects($this->once())->method('findIdsIndexedByPath')->willReturn(['/animaux' => 1]);
        $repository->expects($this->never())->method('find');

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $this->createResolver('/inconnu', $repository, $cache)->forCurrentRequest();
        $this->createResolver('/autre', $repository, $cache)->forCurrentRequest();
    }

    // A row asked for twice in the same request is fetched once
    public function testARowIsFetchedOncePerRequest(): void
    {
        $repository = $this->createMock(UrlMetadataRepository::class);
        $repository->method('findIdsIndexedByPath')->willReturn(['/animaux' => 1]);
        $repository->expects($this->once())->method('find')->with(1)->willReturn($this->createRow('/animaux'));

        $resolver = $this->createResolver('/animaux', $repository);
        $resolver->forCurrentRequest();
        $resolver->forPath('/animaux');
    }

    // What CacheTagListener relies on: once the tag is gone, a new row is seen
    public function testInvalidatingTheTagReloadsTheMap(): void
    {
        $repository = $this->createMock(UrlMetadataRepository::class);
        $repository->expects($this->exactly(2))->method('findIdsIndexedByPath')->willReturn([]);

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $this->createResolver('/animaux', $repository, $cache)->forCurrentRequest();
        $cache->invalidateTags([UrlMetadataResolver::CACHE_TAG]);
        $this->createResolver('/animaux', $repository, $cache)->forCurrentRequest();
    }

    // A site updated but not migrated yet has no table at all. A listing without its description is worth a health check row, never a 500 on every page of the site - and once migrated, the next request sees the table
    public function testAMissingTableIsNotAnErrorAndIsNotCached(): void
    {
        $repository = $this->createMock(UrlMetadataRepository::class);
        $repository->expects($this->exactly(2))->method('findIdsIndexedByPath')->willThrowException(
            $this->createStub(TableNotFoundException::class)
        );

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $this->assertNull($this->createResolver('/animaux', $repository, $cache)->forCurrentRequest());
        $this->createResolver('/animaux', $repository, $cache)->forCurrentRequest();
    }
}
