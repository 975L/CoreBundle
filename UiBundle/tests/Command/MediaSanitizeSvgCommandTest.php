<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\MediaSanitizeSvgCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class MediaSanitizeSvgCommandTest extends TestCase
{
    private const string DIRTY = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)" viewBox="0 0 10 10"><script>alert(2)</script><rect width="10" height="10"/></svg>';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-sanitize-svg-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/medias/catalog');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function write(string $filename, string $content): string
    {
        $path = $this->projectDir . '/public/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    private function runCommand(array $input = []): CommandTester
    {
        $tester = new CommandTester(new MediaSanitizeSvgCommand($this->projectDir));
        $tester->execute($input);

        return $tester;
    }

    // A stored SVG loses its scripts and handlers, in a nested folder too, and a second run finds nothing left to clean
    public function testStoredSvgIsRewrittenOnce(): void
    {
        $path = $this->write('medias/catalog/item-1.svg', self::DIRTY);

        $tester = $this->runCommand();
        $stored = (string) file_get_contents($path);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('medias/catalog/item-1.svg', $tester->getDisplay());
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringContainsString('déjà nettoyés', $this->runCommand()->getDisplay());
    }

    // The dry run lists the file without touching it
    public function testDryRunLeavesTheFileAsItWas(): void
    {
        $path = $this->write('medias/item-1.svg', self::DIRTY);

        $tester = $this->runCommand(['--dry-run' => true]);

        $this->assertStringContainsString('1 SVG à nettoyer', $tester->getDisplay());
        $this->assertSame(self::DIRTY, file_get_contents($path));
    }

    // An SVG that does not parse is listed and kept, and a file that is no SVG is not read as one
    public function testUnreadableSvgIsListedNotRemoved(): void
    {
        $broken = $this->write('medias/item-1.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect');
        $text = $this->write('medias/item-2.txt', 'catalog');

        $tester = $this->runCommand();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 SVG illisible(s)', $tester->getDisplay());
        $this->assertFileExists($broken);
        $this->assertSame('catalog', file_get_contents($text));
    }

    // A site without any medias folder yet has nothing to clean
    public function testNoMediasFolderIsNothingToClean(): void
    {
        new Filesystem()->remove($this->projectDir . '/public/medias');

        $this->assertStringContainsString('déjà nettoyés', $this->runCommand()->getDisplay());
    }
}
