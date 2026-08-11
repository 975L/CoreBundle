<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathCollector;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class BackupPathCollectorTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-backup-paths-test-' . uniqid();
        mkdir($this->projectDir, 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createCollector(array ...$providerPaths): BackupPathCollector
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturn($this->projectDir);

        $providers = array_map(
            static fn (array $paths) => new class ($paths) implements BackupPathProviderInterface {
                public function __construct(private readonly array $paths)
                {
                }

                public function getBackupPaths(): array
                {
                    return $this->paths;
                }
            },
            $providerPaths,
        );

        return new BackupPathCollector($providers, $bag);
    }

    private function create(string $relative): void
    {
        $path = $this->projectDir . '/' . $relative;
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0775, true);
        }
        touch($path);
    }

    // The two modes are not two flavours of the same thing: one is tarred on every run, the other never is
    public function testOnlyThePathsOfTheAskedModeAreReturned(): void
    {
        $this->create('.env.local');
        $this->create('public/medias/photo.jpg');

        $collector = $this->createCollector([
            new BackupPath('.env.local', BackupPath::MODE_ARCHIVE),
            new BackupPath('public/medias', BackupPath::MODE_MIRROR),
        ]);

        $this->assertSame(['.env.local'], $collector->getPaths(BackupPath::MODE_ARCHIVE));
        $this->assertSame(['public/medias'], $collector->getPaths(BackupPath::MODE_MIRROR));
    }

    // Two bundles naming the same folder must not have it archived twice, which would double both the run's duration and the size it reports
    public function testAPathDeclaredByTwoBundlesIsKeptOnce(): void
    {
        $this->create('public/medias/photo.jpg');

        $collector = $this->createCollector(
            [new BackupPath('public/medias', BackupPath::MODE_MIRROR)],
            [new BackupPath('public/medias', BackupPath::MODE_MIRROR)],
        );

        $this->assertSame(['public/medias'], $collector->getPaths(BackupPath::MODE_MIRROR));
    }

    // A bundle declares what it would store, not what this install happens to have: an app without private/medias must not see its backup fail over a folder it never created
    public function testAPathThatIsNotOnDiskIsSkipped(): void
    {
        $collector = $this->createCollector([new BackupPath('private/medias', BackupPath::MODE_MIRROR)]);

        $this->assertSame([], $collector->getPaths(BackupPath::MODE_MIRROR));
    }

    // A path climbing out of the project would be archived under a name no restore could ever put back, and is far likelier to be a typo than an intent
    public function testAPathEscapingTheProjectIsRefused(): void
    {
        $collector = $this->createCollector([new BackupPath('../../etc', BackupPath::MODE_ARCHIVE)]);

        $this->assertSame([], $collector->getPaths(BackupPath::MODE_ARCHIVE));
    }

    // Deduplicating equal paths isn't enough: an app declaring public/medias while a bundle declares public/medias/gallery would have the gallery mirrored twice, to two different places at the destination
    public function testAPathCoveredByADeclaredAncestorIsDropped(): void
    {
        $this->create('public/medias/gallery/photo.jpg');

        $collector = $this->createCollector(
            [new BackupPath('public/medias/gallery', BackupPath::MODE_MIRROR)],
            [new BackupPath('public/medias', BackupPath::MODE_MIRROR)],
        );

        $this->assertSame(['public/medias'], $collector->getPaths(BackupPath::MODE_MIRROR));
    }

    // Sharing a prefix is not being nested: public/medias-old is its own folder, not something public/medias covers
    public function testAFolderMerelySharingAPrefixIsKept(): void
    {
        $this->create('public/medias/photo.jpg');
        $this->create('public/medias-old/photo.jpg');

        $collector = $this->createCollector([
            new BackupPath('public/medias', BackupPath::MODE_MIRROR),
            new BackupPath('public/medias-old', BackupPath::MODE_MIRROR),
        ]);

        $this->assertSame(['public/medias', 'public/medias-old'], $collector->getPaths(BackupPath::MODE_MIRROR));
    }

    // The order bundles happen to be registered in is not a reason for the manifest to change from one run to the next
    public function testThePathsComeBackSorted(): void
    {
        $this->create('public/medias/photo.jpg');
        $this->create('private/invoices/2026.pdf');

        $collector = $this->createCollector([
            new BackupPath('public/medias', BackupPath::MODE_MIRROR),
            new BackupPath('private/invoices', BackupPath::MODE_MIRROR),
        ]);

        $this->assertSame(['private/invoices', 'public/medias'], $collector->getPaths(BackupPath::MODE_MIRROR));
    }
}
