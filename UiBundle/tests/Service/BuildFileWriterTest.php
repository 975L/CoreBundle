<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BuildFileWriter;
use PHPUnit\Framework\TestCase;

class BuildFileWriterTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/build-file-writer-test-' . uniqid();
        mkdir($this->projectDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testWriteCreatesTheBuildDirectoryAndTheFile(): void
    {
        BuildFileWriter::write($this->projectDir, 'theme.css', ':root { --x: 1px; }');

        $this->assertSame(
            ':root { --x: 1px; }',
            file_get_contents($this->projectDir . '/public/bundles/build/theme.css')
        );
    }

    // Every caller rewrites its whole file on each save, so a second write replaces the first rather than appending
    public function testWriteReplacesThePreviousContents(): void
    {
        BuildFileWriter::write($this->projectDir, 'theme.css', 'first');
        BuildFileWriter::write($this->projectDir, 'theme.css', 'second');

        $this->assertSame('second', file_get_contents($this->projectDir . '/public/bundles/build/theme.css'));
    }

    // Written to a temporary file then renamed - none of those may survive the write
    public function testWriteLeavesNoTemporaryFileBehind(): void
    {
        BuildFileWriter::write($this->projectDir, 'theme.css', 'contents');

        $left = glob($this->projectDir . '/public/bundles/build/*.tmp');

        $this->assertSame([], $left);
    }

    public function testWriteThrowsWhenTheDirectoryCannotBeCreated(): void
    {
        // A regular file where the build directory would go, so mkdir() cannot succeed
        file_put_contents($this->projectDir . '/public', 'not a directory');

        $this->expectException(\RuntimeException::class);

        BuildFileWriter::write($this->projectDir, 'theme.css', 'contents');
    }
}
