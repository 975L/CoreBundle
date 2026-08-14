<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Management\UrlMetadataProviderInterface;
use c975L\ConfigBundle\Management\UrlMetadataSynchronizer;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UrlMetadataSynchronizerTest extends TestCase
{
    /** @var list<UrlMetadata> */
    private array $persisted = [];

    private function createSynchronizer(array $declaredPaths, array $existingPaths): UrlMetadataSynchronizer
    {
        $this->persisted = [];

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $existing = [];
        foreach ($existingPaths as $path) {
            $existing[$path] = new UrlMetadata()->setPath($path);
        }

        $repository = $this->createStub(UrlMetadataRepository::class);
        $repository->method('findAllIndexedByPath')->willReturn($existing);

        $provider = new readonly class ($declaredPaths) implements UrlMetadataProviderInterface {
            public function __construct(private array $paths)
            {
            }

            public function getUrlMetadataPaths(): array
            {
                return $this->paths;
            }
        };

        return new UrlMetadataSynchronizer($em, [$provider], $repository);
    }

    // The whole point: a listing added by a release is waiting in the screen instead of having to be typed in
    public function testADeclaredPathWithNoRowGetsAnEmptyOne(): void
    {
        $result = $this->createSynchronizer(['/animaux', '/artefacts'], ['/animaux'])->synchronize();

        $this->assertSame(['/artefacts'], $result['created']);
        $this->assertCount(1, $this->persisted);
        $this->assertSame('/artefacts', $this->persisted[0]->getPath());
        $this->assertNull($this->persisted[0]->getSummarySocialNetwork());
    }

    // Runs at every deployment, so it must never touch what has been written
    public function testARowAlreadyThereIsLeftAlone(): void
    {
        $result = $this->createSynchronizer(['/animaux'], ['/animaux'])->synchronize();

        $this->assertSame([], $result['created']);
        $this->assertSame([], $this->persisted);
    }

    // Reported, never deleted: an url can leave a listing for one release and come back, and the sentence written for it is work
    public function testARowNoLongerDeclaredIsReportedAndKept(): void
    {
        $result = $this->createSynchronizer(['/animaux'], ['/animaux', '/ancienne-liste'])->synchronize();

        $this->assertSame(['/ancienne-liste'], $result['orphaned']);
        $this->assertSame([], $this->persisted);
    }

    // Paths are normalised on the way in, exactly as UrlMetadata::setPath() does - a provider writing "animaux" declares the same url as one writing "/animaux/"
    public function testAPathIsNormalisedBeforeBeingCompared(): void
    {
        $result = $this->createSynchronizer(['animaux/'], ['/animaux'])->synchronize();

        $this->assertSame([], $result['created']);
        $this->assertSame([], $result['orphaned']);
    }

    // Two bundles may legitimately declare the same url - one serving the listing, the other linking to it - and the unique constraint would refuse the second row
    public function testTheSamePathDeclaredTwiceCreatesOneRow(): void
    {
        $result = $this->createSynchronizer(['/animaux', '/animaux/'], [])->synchronize();

        $this->assertSame(['/animaux'], $result['created']);
        $this->assertCount(1, $this->persisted);
        $this->assertSame(1, $result['declared']);
    }
}
