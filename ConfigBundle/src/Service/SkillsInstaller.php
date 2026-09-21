<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

// Puts the agent skills the installed bundles ship where a coding agent actually looks for them. A skill sitting in vendor/ is read by nobody: agents load them from .claude/skills/ alone, which is why every bundle shipping one ends up asking its users to symlink it by hand. Symlinks rather than copies: the link is what the package currently ships, at every composer update and with nothing to refresh, where a copy silently describes the version installed the day it was made
class SkillsInstaller
{
    private const string SKILLS_DIR = '.claude/skills';

    /**
     * @param array<string, array{path: string, namespace: string}> $bundlesMetadata
     */
    public function __construct(
        #[Autowire(param: 'kernel.bundles_metadata')]
        private readonly array $bundlesMetadata,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Links every skill the registered bundles ship into .claude/skills/ and deletes the dead links of the ones that stopped shipping theirs, read off the kernel's whole bundle list rather than off the c975L ones alone (see BundleLocator): skills/<name>/SKILL.md is the agentskills.io layout, EasyAdmin 5.6 ships one that way too, and a bundle adopting it tomorrow is installed here without a line of code
    /** @return array{linked: list<string>, skipped: int, kept: list<string>, pruned: list<string>} */
    public function install(bool $dryRun = false): array
    {
        $result = ['linked' => [], 'skipped' => 0, 'kept' => [], 'pruned' => []];
        $skills = $this->shippedSkills();
        $skillsDir = $this->projectDir . '/' . self::SKILLS_DIR;

        if (!$dryRun && $skills && !is_dir($skillsDir)) {
            new Filesystem()->mkdir($skillsDir);
        }

        foreach ($skills as $name => $source) {
            $this->linkSkill($name, $source, $skillsDir, $dryRun, $result);
        }

        $result['pruned'] = $this->pruneDeadLinks($skillsDir, $dryRun);

        return $result;
    }

    // Each skills/<name>/SKILL.md the registered bundles ship, keyed by the name the agent loads it under
    /** @return array<string, string> */
    private function shippedSkills(): array
    {
        $skills = [];

        foreach ($this->bundlesMetadata as $metadata) {
            foreach (glob($metadata['path'] . '/skills/*', \GLOB_ONLYDIR) ?: [] as $directory) {
                if (is_file($directory . '/SKILL.md')) {
                    $skills[basename($directory)] = $directory;
                }
            }
        }

        ksort($skills);

        return $skills;
    }

    // One shipped skill weighed against whatever answers to its name in .claude/skills/ already
    private function linkSkill(string $name, string $source, string $skillsDir, bool $dryRun, array &$result): void
    {
        $target = $skillsDir . '/' . $name;

        // Anything there that is not a link is the site's own skill of that name, and overwriting it would delete work no re-run brings back
        if (file_exists($target) && !is_link($target)) {
            $result['kept'][] = self::SKILLS_DIR . '/' . $name;

            return;
        }

        if (is_link($target) && realpath($target) === realpath($source)) {
            ++$result['skipped'];

            return;
        }

        $result['linked'][] = self::SKILLS_DIR . '/' . $name;

        if ($dryRun) {
            return;
        }

        // Relative, so that the link keeps pointing at the package for a site committing its .claude/ directory, and a stale one is replaced rather than nested inside the directory it points at
        $filesystem = new Filesystem();
        $filesystem->remove($target);
        $filesystem->symlink(rtrim($filesystem->makePathRelative($source, $skillsDir), '/'), $target);
    }

    // The links left behind by a skill a bundle renamed, withdrew, or took away with it when it was uninstalled - deleted, an agent reading a directory of broken links being told a skill exists that nothing answers for
    /** @return list<string> */
    private function pruneDeadLinks(string $skillsDir, bool $dryRun): array
    {
        $pruned = [];

        foreach (glob($skillsDir . '/*') ?: [] as $entry) {
            if (is_link($entry) && !file_exists($entry)) {
                $pruned[] = self::SKILLS_DIR . '/' . basename($entry);

                if (!$dryRun) {
                    unlink($entry);
                }
            }
        }

        return $pruned;
    }
}
