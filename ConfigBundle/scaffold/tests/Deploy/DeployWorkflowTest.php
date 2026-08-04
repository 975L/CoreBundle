<?php

namespace App\Tests\Deploy;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\CommandNotFoundException;

// A deployment workflow calls its console commands as plain strings inside YAML, which none of this project's gates ever reads - php-cs-fixer, PHPStan and the rest of this suite all stop at PHP. A command a bundle renamed, or one written against a version composer.lock does not carry yet, is then discovered by a deployment that stops halfway through: workers already restarted, remaining steps skipped, and a server left in a state nobody chose. These two tests are what puts .github/workflows/ under the same suite as the code.
// Both are no-ops where there is no site to check (no workflow to read, no vendor/ to look at), so this file also travels in the bundle's own scaffold suite, where no application kernel exists.
class DeployWorkflowTest extends KernelTestCase
{
    // Every "bin/console <command>" of every workflow has to be a command this site actually has. Resolved exactly as the console itself resolves it, through find() rather than has(): a workflow legitimately calls doctrine:migration:migrate, which is no registered name at all but an unambiguous abbreviation of doctrine:migrations:migrate. An abbreviation that several commands answer to throws here too, and rightly so - it would stop the deployment the same way a missing command does.
    public function testEveryCommandTheWorkflowsCallIsInstalled(): void
    {
        $workflows = glob(self::projectDir() . '/.github/workflows/*.y*ml') ?: [];

        if ([] === $workflows) {
            $this->markTestSkipped('This project has no GitHub Actions workflow to check.');
        }

        $application = new Application(self::bootKernel());

        $missing = [];
        foreach ($workflows as $workflow) {
            // Line continuations first, so a command written on the next line is still read next to its "bin/console"
            $contents = preg_replace('#\\\\\s*\R\s*#', ' ', (string) file_get_contents($workflow));

            // Options are skipped over: "bin/console --env=prod cache:clear" names cache:clear, not --env
            preg_match_all('#bin/console\s+(?:--?\S+\s+)*([\w][\w:-]*)#', (string) $contents, $matches);

            foreach (array_unique($matches[1]) as $command) {
                try {
                    $application->find($command);
                } catch (CommandNotFoundException) {
                    $missing[] = basename($workflow) . ': ' . $command;
                }
            }
        }

        $this->assertSame([], $missing, 'Called by a workflow, installed nowhere: the command was either renamed by the bundle that ships it, or belongs to a version composer.lock has not been updated to yet.');
    }

    // A package symlinked to a local working copy makes this whole suite answer for code the deployment will never see, composer.lock still pointing at the released version - which is exactly how a workflow ends up calling a command that only exists next door. Whatever is green here has to be green from the lock alone.
    public function testNoPackageIsInstalledAsASymlink(): void
    {
        $packages = glob(self::projectDir() . '/vendor/*/*', GLOB_ONLYDIR) ?: [];

        // is_dir() on top of GLOB_ONLYDIR, which several systems treat as a hint only: it keeps the symlinked packages and drops vendor/bin's symlinks to files
        $symlinked = array_filter($packages, static fn (string $path): bool => is_link($path) && is_dir($path));

        $names = array_map(static fn (string $path): string => basename(\dirname($path)) . '/' . basename($path), $symlinked);

        $this->assertSame([], array_values($names), 'Installed as a symlink to a local working copy: run "composer update" on the package named above to take the released version back before pushing.');
    }

    // tests/ sits at the root of the project, and what both tests ask about is that same tree - reading it from this file rather than from kernel.project_dir keeps the symlink test free of a kernel it has no use for
    private static function projectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
