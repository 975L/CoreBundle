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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UrlMetadataResolverTest extends TestCase
{
    private function createResolver(?string $currentPath, UrlMetadataRepository $repository): UrlMetadataResolver
    {
        $requestStack = new RequestStack();
        if (null !== $currentPath) {
            $requestStack->push(Request::create($currentPath));
        }

        return new UrlMetadataResolver($requestStack, $repository);
    }

    private function createRepository(array $rows): UrlMetadataRepository
    {
        $repository = $this->createStub(UrlMetadataRepository::class);
        $repository->method('findAllIndexedByPath')->willReturn($rows);

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

    // One query per request and not one per lookup: the layouts ask for the current row, and whatever else asks afterwards must not pay for it again
    public function testTheTableIsReadOnlyOnce(): void
    {
        $repository = $this->createMock(UrlMetadataRepository::class);
        $repository->expects($this->once())
            ->method('findAllIndexedByPath')
            ->willReturn(['/animaux' => $this->createRow('/animaux')]);

        $resolver = $this->createResolver('/animaux', $repository);
        $resolver->forCurrentRequest();
        $resolver->forPath('/animaux');
        $resolver->forPath('/inconnu');
    }

    // A site updated but not migrated yet has no table at all. A listing without its description is worth a health check row, never a 500 on every page of the site
    public function testAMissingTableIsNotAnError(): void
    {
        $repository = $this->createStub(UrlMetadataRepository::class);
        $repository->method('findAllIndexedByPath')->willThrowException(
            $this->createStub(TableNotFoundException::class)
        );

        $this->assertNull($this->createResolver('/animaux', $repository)->forCurrentRequest());
    }
}
