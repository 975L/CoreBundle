<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\FileCounter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class FileCounterTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/c975l-file-counter-test-' . uniqid();
        mkdir($this->folder . '/nested', 0775, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->folder);
    }

    // Every level counted, the folders themselves counted as nothing: a mirrored medias folder is a tree, and what both callers reason about is files
    public function testFilesAreCountedThroughTheWholeTree(): void
    {
        file_put_contents($this->folder . '/photo.jpg', 'photo');
        file_put_contents($this->folder . '/nested/other.jpg', 'other');

        $this->assertSame(2, FileCounter::count($this->folder));
    }

    // A declared path may name a single file rather than a folder, .env.local being one
    public function testASingleFileCountsAsOne(): void
    {
        file_put_contents($this->folder . '/photo.jpg', 'photo');

        $this->assertSame(1, FileCounter::count($this->folder . '/photo.jpg'));
    }

    // Zero rather than the exception a RecursiveDirectoryIterator raises: this is what a folder that has just been lost looks like, and the deletion guard reading that number has to keep working precisely then
    public function testAMissingPathCountsAsNothing(): void
    {
        $this->assertSame(0, FileCounter::count($this->folder . '/gone'));
    }
}
