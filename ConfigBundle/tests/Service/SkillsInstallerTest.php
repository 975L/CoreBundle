<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\SkillsInstaller;
use PHPUnit\Framework\TestCase;

class SkillsInstallerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir() . '/c975l-skills-installer-test-' . uniqid();
        mkdir($directory, 0775, true);

        // The real path, the temporary directory being reached through a symlink on some systems - which is exactly what install() compares a link against
        $this->projectDir = (string) realpath($directory);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // Recursively deletes a directory tree, never following the symlinks this test is made of
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
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    // Fabricates vendor/<package>/skills/<skill>/SKILL.md for each skill named, and reports the package the way the kernel would
    private function installer(array $packages): SkillsInstaller
    {
        $metadata = [];

        foreach ($packages as $package => $skills) {
            $path = $this->projectDir . '/vendor/' . $package;

            foreach ($skills as $skill) {
                mkdir($path . '/skills/' . $skill, 0775, true);
                file_put_contents($path . '/skills/' . $skill . '/SKILL.md', '# ' . $skill);
            }

            $metadata[$package] = ['path' => $path, 'namespace' => 'Acme\\Test'];
        }

        return new SkillsInstaller($metadata, $this->projectDir);
    }

    private function target(string $skill): string
    {
        return $this->projectDir . '/.claude/skills/' . $skill;
    }

    public function testItLinksEverySkillTheBundlesShip(): void
    {
        $result = $this->installer(['c975l/core-bundle' => ['c975l-config'], 'easycorp/easyadmin-bundle' => ['easyadmin']])->install();

        $this->assertSame(['.claude/skills/c975l-config', '.claude/skills/easyadmin'], $result['linked']);
        $this->assertSame(0, $result['skipped']);
        $this->assertTrue(is_link($this->target('easyadmin')));
        $this->assertFileExists($this->target('easyadmin') . '/SKILL.md');
    }

    // Relative, so the link survives a project moved or committed whole rather than pointing at the absolute path of the machine that ran the command
    public function testItLinksRelativelyToTheProject(): void
    {
        $this->installer(['c975l/core-bundle' => ['c975l-config']])->install();

        $this->assertSame('../../vendor/c975l/core-bundle/skills/c975l-config', readlink($this->target('c975l-config')));
    }

    public function testASecondRunChangesNothing(): void
    {
        $installer = $this->installer(['c975l/core-bundle' => ['c975l-config']]);
        $installer->install();

        $result = $installer->install();

        $this->assertSame([], $result['linked']);
        $this->assertSame(1, $result['skipped']);
    }

    // A directory of its own carrying the name of a shipped skill holds work this command must never delete
    public function testItNeverTouchesASkillTheSiteWroteItself(): void
    {
        $installer = $this->installer(['c975l/core-bundle' => ['c975l-config']]);
        mkdir($this->projectDir . '/.claude/skills/c975l-config', 0775, true);
        file_put_contents($this->target('c975l-config') . '/SKILL.md', '# the site\'s own');

        $result = $installer->install();

        $this->assertSame([], $result['linked']);
        $this->assertSame(['.claude/skills/c975l-config'], $result['kept']);
        $this->assertStringEqualsFile($this->target('c975l-config') . '/SKILL.md', '# the site\'s own');
    }

    // What a bundle renaming, withdrawing or uninstalling a skill leaves behind
    public function testItDeletesTheLinksOfSkillsNoBundleShipsAnymore(): void
    {
        $installer = $this->installer(['c975l/core-bundle' => ['c975l-config']]);
        $installer->install();
        rename($this->projectDir . '/vendor/c975l/core-bundle/skills/c975l-config', $this->projectDir . '/vendor/c975l/core-bundle/skills/c975l-settings');

        $result = new SkillsInstaller(
            ['c975l/core-bundle' => ['path' => $this->projectDir . '/vendor/c975l/core-bundle', 'namespace' => 'Acme\\Test']],
            $this->projectDir
        )->install();

        $this->assertSame(['.claude/skills/c975l-settings'], $result['linked']);
        $this->assertSame(['.claude/skills/c975l-config'], $result['pruned']);
        $this->assertFalse(is_link($this->target('c975l-config')));
    }

    public function testADryRunWritesNothing(): void
    {
        $result = $this->installer(['c975l/core-bundle' => ['c975l-config']])->install(true);

        $this->assertSame(['.claude/skills/c975l-config'], $result['linked']);
        $this->assertDirectoryDoesNotExist($this->projectDir . '/.claude');
    }
}
