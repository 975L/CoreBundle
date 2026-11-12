<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\ArchivePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ArchivePathTest extends TestCase
{
    private string $root;

    private string $filesDir;

    // An extracted archive holding one media, beside a file it must never reach
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/archive_path_' . bin2hex(random_bytes(4));
        $this->filesDir = $this->root . '/files';
        mkdir($this->filesDir . '/medias', 0775, true);
        file_put_contents($this->filesDir . '/medias/item-1.jpg', 'image');
        file_put_contents($this->root . '/secret.env', 'APP_SECRET=1');
    }

    // The extracted archive removed
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
    }

    // A file the archive holds resolves to its real path
    public function testAFileInsideTheFolderResolves(): void
    {
        $this->assertSame(realpath($this->filesDir . '/medias/item-1.jpg'), ArchivePath::resolve($this->filesDir, 'medias/item-1.jpg'));
    }

    // Whatever would leave the folder, or names nothing, is refused
    #[DataProvider('refusedPaths')]
    public function testAPathOutOfTheFolderIsRefused(mixed $relative): void
    {
        $this->assertNull(ArchivePath::resolve($this->filesDir, $relative));
    }

    // Each way a manifest could name something it must not
    /** @return iterable<string, array{mixed}> */
    public static function refusedPaths(): iterable
    {
        yield 'parent segment' => ['../secret.env'];
        yield 'parent segment deeper' => ['medias/../../secret.env'];
        yield 'backslash parent segment' => ['medias\\..\\..\\secret.env'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'nul byte' => ["medias/item-1.jpg\0.php"];
        yield 'empty' => [''];
        yield 'not a string' => [['medias/item-1.jpg']];
        yield 'null' => [null];
        yield 'missing file' => ['medias/item-2.jpg'];
        yield 'a folder' => ['medias'];
    }

    // A symlink the archive carries cannot take the path out of the folder
    public function testASymlinkPointingOutsideIsRefused(): void
    {
        symlink($this->root . '/secret.env', $this->filesDir . '/medias/link.jpg');

        $this->assertNull(ArchivePath::resolve($this->filesDir, 'medias/link.jpg'));
    }
}
