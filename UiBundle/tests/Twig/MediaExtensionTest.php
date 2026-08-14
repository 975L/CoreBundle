<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Twig\MediaExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Extension\AttributeExtension;

class MediaExtensionTest extends TestCase
{
    // Real in-memory tag-aware pool (not a stub): these tests care about how many times the repository is actually hit across cache hits/misses, which is easier to get right against a real pool than to stub faithfully
    private function createCache(): TagAwareCacheInterface
    {
        return new TagAwareAdapter(new ArrayAdapter());
    }

    // "logo" is one of Media::getSingletonRoles() - resolved via the batch preload, never an individual findOneByRole() call
    public function testGetSiteMediaResolvesASingletonRoleFromTheBatchPreload(): void
    {
        $media = new Media()->setRole('logo');
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())->method('findBySingletonRoles')->willReturn([$media]);
        $repository->expects($this->never())->method('findOneByRole');

        $extension = new MediaExtension($repository, $this->createCache());

        $this->assertSame($media, $extension->getSiteMedia('logo'));
    }

    // A singleton role with no row yet (e.g. no favicon uploaded) must resolve to null from the preload alone, without falling back to an individual query
    public function testGetSiteMediaReturnsNullForASingletonRoleMissingFromThePreload(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())->method('findBySingletonRoles')->willReturn([]);
        $repository->expects($this->never())->method('findOneByRole');

        $extension = new MediaExtension($repository, $this->createCache());

        $this->assertNull($extension->getSiteMedia('favicon'));
    }

    // A role outside Media::getSingletonRoles() (e.g. the repeatable "error-image" pool) isn't covered by the batch preload - still falls back to an individual findOneByRole() call
    public function testGetSiteMediaFallsBackToFindOneByRoleForANonSingletonRole(): void
    {
        $media = new Media();
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('findBySingletonRoles')->willReturn([]);
        $repository->expects($this->once())->method('findOneByRole')->with('error-image')->willReturn($media);

        $extension = new MediaExtension($repository, $this->createCache());

        $this->assertSame($media, $extension->getSiteMedia('error-image'));
    }

    // A base layout typically asks for several distinct roles (logo, favicon...) from several places in one request - only the first call of any of them should trigger the batch preload
    public function testGetSiteMediaPreloadsSingletonRolesOnlyOnce(): void
    {
        $logo = new Media()->setRole('logo');
        $favicon = new Media()->setRole('favicon');
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())->method('findBySingletonRoles')->willReturn([$logo, $favicon]);

        $extension = new MediaExtension($repository, $this->createCache());

        $this->assertSame($logo, $extension->getSiteMedia('logo'));
        $this->assertSame($favicon, $extension->getSiteMedia('favicon'));
        $this->assertSame($logo, $extension->getSiteMedia('logo'));
    }

    // A non-singleton role's result must also be memoized - otherwise every call for it would keep re-querying
    public function testGetSiteMediaMemoizesANonSingletonRole(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('findBySingletonRoles')->willReturn([]);
        $repository->expects($this->once())->method('findOneByRole')->with('missing-role')->willReturn(null);

        $extension = new MediaExtension($repository, $this->createCache());

        $this->assertNull($extension->getSiteMedia('missing-role'));
        $this->assertNull($extension->getSiteMedia('missing-role'));
    }

    // The whole point of a cross-request cache: a fresh MediaExtension instance (simulating a new request) sharing the same cache pool must not hit the repository again
    public function testGetSiteMediaSurvivesAcrossInstancesSharingTheSameCachePool(): void
    {
        $media = new Media()->setRole('logo');
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())->method('findBySingletonRoles')->willReturn([$media]);

        $cache = $this->createCache();
        $firstRequest = new MediaExtension($repository, $cache);
        $this->assertEquals($media, $firstRequest->getSiteMedia('logo'));

        $secondRequest = new MediaExtension($repository, $cache);
        $this->assertEquals($media, $secondRequest->getSiteMedia('logo'));
    }

    // "random" means a fresh pick is the whole point - getRandomSiteMedia() must never be memoized nor go through the singleton preload
    public function testGetRandomSiteMediaDelegatesToRepositoryFindRandomByRole(): void
    {
        $media = new Media();
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())->method('findRandomByRole')->with('error-image')->willReturn($media);
        $repository->expects($this->never())->method('findBySingletonRoles');

        $extension = new MediaExtension($repository, $this->createCache());

        $this->assertSame($media, $extension->getRandomSiteMedia('error-image'));
    }

    public function testGetFunctionsRegistersSiteMediaFunctions(): void
    {
        $extension = new MediaExtension($this->createStub(MediaRepository::class), $this->createCache());
        $names = array_map(fn ($function) => $function->getName(), new AttributeExtension(MediaExtension::class)->getFunctions());

        $this->assertSame(['site_media', 'site_random_media'], $names);
    }
}
