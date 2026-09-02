<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

// Answers the one question "c975l:scaffold:install" leaves open when it reports a customized file: whether the scaffold has changed since the version this site started from. Both stories end in the same warning today - a file the site rewrote on purpose and a file whose upstream moved on without it - and a developer coming back to it months later cannot tell them apart without hunting the bundle's history by hand
class ScaffoldDiffer
{
    // The versions of one file the history is walked back through, past which a base older than the site's whole scaffold era is not worth the processes it would cost
    private const int COMMIT_LIMIT = 200;

    public function __construct(
        private readonly ScaffoldInstaller $scaffoldInstaller,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Every file "c975l:scaffold:install" would leave untouched, each weighed against the version it was delivered from.
     * 'upstream' is the whole point: empty means there is nothing left to carry over - the scaffold never moved since,
     * or what it gained has already been reported here by hand - so the divergence is this site's own work and the
     * warning is noise. A diff means the bundle gained something this site is still missing.
     * A base that could not be recovered leaves 'upstream' null and 'fallback' holding the plain local-vs-scaffold diff,
     * which is all any of this could say before the manifest existed.
     * $bundleSources are directories holding git clones of the bundles (a clone itself, or a directory of them), used
     * when the site's own history cannot answer - see baseFromBundles().
     *
     * 'unmatched' carries the given paths no scaffold file answered to, a typo reading exactly like a site with
     * nothing to report otherwise.
     *
     * @return array{files: list<array{file: string, source: string, base: ?string, upstream: ?string, fallback: ?string}>, unmatched: list<string>}
     */
    public function diff(array $paths = [], array $bundleSources = []): array
    {
        $result = $this->scaffoldInstaller->install($paths, true);
        if (!$result['diverged']) {
            return ['files' => [], 'unmatched' => $result['unmatched']];
        }

        $manifest = $this->scaffoldInstaller->manifest();
        $repositories = $this->repositories($bundleSources);

        $files = [];
        foreach ($result['diverged'] as $file => $source) {
            $files[] = $this->weigh($file, $source, $manifest[$file] ?? null, $repositories);
        }

        return ['files' => $files, 'unmatched' => $result['unmatched']];
    }

    /**
     * Records the current scaffold as the base of every file this site customized, which is how a report it has read
     * and turned down stops coming back - see ScaffoldInstaller::acknowledge().
     *
     * 'unmatched' carries the given paths no scaffold file answered to, for the same reason diff() does.
     *
     * @return array{files: list<string>, unmatched: list<string>}
     */
    public function acknowledge(array $paths = []): array
    {
        return $this->scaffoldInstaller->acknowledge($paths);
    }

    // A scaffold source resolved outside the project (a Composer "path" repository, symlinked) comes back absolute from ScaffoldInstaller and must not be prefixed a second time
    private function absolutePath(string $source): string
    {
        return str_starts_with($source, '/') ? $source : $this->projectDir . '/' . $source;
    }

    // One divergence, told from its base when one can be recovered and from the two files alone otherwise
    private function weigh(string $file, string $source, ?string $hash, array $repositories): array
    {
        // The recorded hash being the source's own, there is nothing to look up and nothing to compare: the bundles still ship the version this site started from (or the one it acknowledged), so whatever the file holds is its own doing
        if (null !== $hash && $hash === hash_file('sha256', $this->absolutePath($source))) {
            return ['file' => $file, 'source' => $source, 'base' => 'the version recorded here', 'upstream' => '', 'fallback' => null];
        }

        $base = null === $hash ? null : $this->base($file, $source, $hash, $repositories);

        if (null === $base) {
            return ['file' => $file, 'source' => $source, 'base' => null, 'upstream' => null, 'fallback' => OutputFormatter::escape($this->unifiedDiff($this->absolutePath($source), $this->projectDir . '/' . $file))];
        }

        $baseFile = tempnam(sys_get_temp_dir(), 'c975l-scaffold-base-');
        file_put_contents($baseFile, $base['content']);
        $upstream = $this->stillMissing($this->unifiedDiff($baseFile, $this->absolutePath($source)), $this->projectDir . '/' . $file);
        unlink($baseFile);

        return ['file' => $file, 'source' => $source, 'base' => $base['origin'], 'upstream' => OutputFormatter::escape($upstream), 'fallback' => null];
    }

    // The version this site was delivered, looked for by its recorded hash in the two histories that can hold it: the site's own, where the file was committed as it landed, then the bundles', which is the only one left when the site committed the delivery and its customization in one go - what "ComposerUpdate.sh" does on every run, so the second source is the rule rather than the exception
    private function base(string $file, string $source, string $hash, array $repositories): ?array
    {
        $found = $this->findBlob($this->projectDir, $file, $hash);
        if (null !== $found) {
            return ['content' => $found['content'], 'origin' => sprintf('this site (%s)', substr($found['commit'], 0, 7))];
        }

        return $this->baseFromBundles($source, $hash, $repositories);
    }

    // The same hash looked for in each clone, at the path the file has inside its bundle rather than inside vendor/ - a package is free to ship several bundles, so everything past "vendor/<vendor>/<package>/" is kept as it stands
    private function baseFromBundles(string $source, string $hash, array $repositories): ?array
    {
        if (!str_starts_with($source, 'vendor/')) {
            return null;
        }

        $path = preg_replace('#^vendor/[^/]+/[^/]+/#', '', $source);

        foreach ($repositories as $repository) {
            $found = $this->findBlob($repository, $path, $hash);
            if (null !== $found) {
                return ['content' => $found['content'], 'origin' => sprintf('%s (%s)', basename($repository), substr($found['commit'], 0, 7))];
            }
        }

        return null;
    }

    // The most recent version of that path whose content is the recorded one - git addresses a blob by its own sha1, which says nothing about the sha256 the manifest holds, hence reading each version rather than asking git for it
    private function findBlob(string $repository, string $path, string $hash): ?array
    {
        $log = $this->git($repository, ['log', '--format=%H', '--max-count=' . self::COMMIT_LIMIT, '--', $path]);
        if (null === $log) {
            return null;
        }

        foreach (array_filter(explode("\n", trim($log))) as $commit) {
            $content = $this->git($repository, ['show', $commit . ':' . $path]);
            if (null !== $content && hash('sha256', $content) === $hash) {
                return ['commit' => $commit, 'content' => $content];
            }
        }

        return null;
    }

    // The git repositories to search, each given directory being either a clone itself or a directory holding several
    private function repositories(array $bundleSources): array
    {
        $repositories = [];

        foreach ($bundleSources as $source) {
            foreach ([$source, ...(glob(rtrim($source, '/') . '/*', \GLOB_ONLYDIR) ?: [])] as $directory) {
                if (is_dir($directory . '/.git')) {
                    $repositories[] = $directory;
                }
            }
        }

        return array_values(array_unique($repositories));
    }

    // What the scaffold gained, minus the hunks this site has already carried over by hand: a file customized once is customized for good, so the same "you are missing this" would come back on every update long after it was answered - and an answer nobody can record is one nobody trusts. Judged line by line rather than by position, the reported line landing wherever this site's own markup has room for it. A hunk stays as soon as one line it adds is nowhere in the file, or one line it removes is still there
    private function stillMissing(string $diff, string $target): string
    {
        if ('' === $diff || !is_file($target)) {
            return $diff;
        }

        $local = array_map(trim(...), explode("\n", (string) file_get_contents($target)));

        $kept = [];
        foreach (preg_split('/^(?=@@ )/m', $diff, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $hunk) {
            if ($this->answered($hunk, $local)) {
                continue;
            }

            $kept[] = $hunk;
        }

        return implode('', $kept);
    }

    // Whether this site holds what the hunk carries - a blank line is no evidence of anything, and neither is a hunk whose every line is accounted for
    private function answered(string $hunk, array $local): bool
    {
        foreach (explode("\n", $hunk) as $line) {
            $content = trim(substr($line, 1));
            if ('' === $content) {
                continue;
            }

            if (
                (str_starts_with($line, '+') && !\in_array($content, $local, true))
                || (str_starts_with($line, '-') && \in_array($content, $local, true))
            ) {
                return false;
            }
        }

        return true;
    }

    // Only the hunks: the header git writes names two files by their path on disk, one of them a temporary one, where the caller is already showing which file this is about
    private function unifiedDiff(string $left, string $right): string
    {
        $process = new Process(['git', 'diff', '--no-index', '--no-color', '--', $left, $right]);
        $process->run();

        $output = $process->getOutput();
        $hunk = strpos($output, "\n@@");

        return false === $hunk ? '' : substr($output, $hunk + 1);
    }

    // Null rather than an exception whatever went wrong: git missing, a directory that is no repository, a path it never held - each simply means this history cannot answer, and the next one is asked
    private function git(string $repository, array $arguments): ?string
    {
        $process = new Process(['git', ...$arguments], $repository);
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
