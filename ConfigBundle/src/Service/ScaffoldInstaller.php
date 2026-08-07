<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\UiBundle\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

class ScaffoldInstaller
{
    private const SCAFFOLD_DIRS = ['src', 'templates', 'tests', 'translations', 'assets'];

    private const THEMES_DIR_NAME = 'themes';

    private const THEMES_DIR = 'assets/styles/' . self::THEMES_DIR_NAME;

    private const THEME_PROVIDER = 'src/Service/ThemeStylesheetProvider.php';

    // Hash of every scaffold file as it was last delivered here, which is what tells a file the site customized from a file the scaffold itself moved on from - the two are indistinguishable by content alone. Versioned like symfony.lock: a site without it (or having lost it) is simply treated as having customized everything, which is the safe way round
    private const MANIFEST_FILE = '.c975l-scaffold.json';

    // The scaffold files a bundle has withdrawn, mapped to the hashes of every version it ever delivered ({"src/Security/EmailVerifier.php": ["<sha256>", …]}). Read from the bundle rather than deduced from what the manifest holds and the scaffold no longer ships: an uninstalled bundle takes its whole scaffold directory with it, and its files are then no more "withdrawn" than the site's own work is. It also reaches the sites whose file predates the manifest, which is every site for anything withdrawn before it existed. Sits beside scaffold/{src,templates,…} rather than inside one of them, where the Finder would copy it into the project
    private const REMOVED_FILE = 'scaffold/removed.json';

    public function __construct(
        private readonly BundleLocator $bundleLocator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // Copies scaffold/{src,templates,tests,translations,assets} from every installed c975L bundle into the project - a target already identical to the scaffold source is left untouched (no backup, no copy), so re-running this on an unmodified project is a no-op. A target that differs is decided by MANIFEST_FILE rather than overwritten on sight: still bit-for-bit what was last delivered here means the site never touched it and the scaffold moved on, so it is refreshed silently; anything else is the site's own work, is left exactly as it is, and comes back under 'diverged' for the caller to report. $force takes the old behaviour back for those - overwrite, after backing the file up into existingFiles/<same relative path>.old - which is what a site adopting a new scaffold wholesale wants, ideally narrowed by $paths. 'assets' stays outside all of it: once a target exists there it is the app's own editable file, whatever its content (see themeImportReminder()). The withdrawn files each bundle declares in REMOVED_FILE are deleted under the same rule, see remove(). $paths restricts the run to the relative paths given ('src/Scheduler', or a single file), $dryRun reports what would happen without writing anything. The returned 'unmatched' lists the given paths no scaffold file answered to, a run restricted to nothing at all being indistinguishable from an up-to-date site otherwise
    public function install(array $paths = [], bool $dryRun = false, bool $force = false): array
    {
        $copied = 0;
        $backedUp = 0;
        $skipped = 0;
        $files = [];
        $diverged = [];
        $delivered = [];
        $matchedPaths = [];
        $manifest = $this->readManifest();

        foreach ($this->bundleLocator->directories() as $bundleDir) {
            foreach (self::SCAFFOLD_DIRS as $dir) {
                $scaffoldDir = $bundleDir . '/scaffold/' . $dir;
                if (!is_dir($scaffoldDir)) {
                    continue;
                }

                $finder = (new Finder())->files()->in($scaffoldDir);
                foreach ($finder as $file) {
                    $relativePath = $dir . '/' . $file->getRelativePathname();
                    $target = $this->projectDir . '/' . $relativePath;

                    // Recorded before the $paths filter and whatever this file's fate is: what remove() must not delete is what some bundle still ships, which a run narrowed to another path doesn't stop being true of
                    $delivered[$relativePath] = true;

                    if ($paths) {
                        $matched = array_filter($paths, static fn (string $path): bool => self::matches($relativePath, $path));
                        if (!$matched) {
                            continue;
                        }

                        // Kept whatever the file's fate is (copied, backed up, or already identical): the path did name something, which is all the caller needs to tell a real no-op from a typo
                        $matchedPaths = array_merge($matchedPaths, $matched);
                    }

                    $sourceHash = hash_file('sha256', $file->getPathname());

                    if (is_file($target)) {
                        // 'assets' is the app's own editable copy from the first install onward (see themeImportReminder()) - unlike src/templates/tests/translations, it's never backed up/overwritten again once it exists, whether its content differs or not
                        if ('assets' === $dir) {
                            ++$skipped;
                            continue;
                        }

                        $targetHash = hash_file('sha256', $target);
                        if ($targetHash === $sourceHash) {
                            // Records the hash on the way past, so a site predating the manifest stops being seen as having customized every file it never touched
                            $manifest[$relativePath] = $sourceHash;
                            ++$skipped;
                            continue;
                        }

                        $customized = $targetHash !== ($manifest[$relativePath] ?? null);

                        if ($customized && !$force) {
                            $diverged[$relativePath] = $this->relativeToProject($file->getPathname());
                            continue;
                        }

                        // Only what the site wrote is worth keeping: a file still identical to what was delivered is reproducible from the bundle it came from, so refreshing it needs no backup
                        if ($customized) {
                            if (!$dryRun) {
                                $this->backup($relativePath, $target);
                            }
                            ++$backedUp;
                        }
                    }

                    if (!$dryRun) {
                        if (!is_dir(\dirname($target))) {
                            mkdir(\dirname($target), 0775, true);
                        }
                        copy($file->getPathname(), $target);
                    }
                    $manifest[$relativePath] = $sourceHash;
                    ++$copied;
                    $files[] = $relativePath;
                }
            }
        }

        $deleted = [];
        $obsolete = [];

        foreach ($this->bundleLocator->directories() as $bundle => $bundleDir) {
            foreach ($this->readRemoved($bundleDir) as $relativePath => $hashes) {
                // A path another bundle (or this one, from another directory) still ships was moved, not withdrawn - it has just been delivered above
                if (isset($delivered[$relativePath])) {
                    continue;
                }

                if ($paths) {
                    $matched = array_filter($paths, static fn (string $path): bool => self::matches($relativePath, $path));
                    if (!$matched) {
                        continue;
                    }

                    $matchedPaths = array_merge($matchedPaths, $matched);
                }

                $target = $this->projectDir . '/' . $relativePath;
                if (!is_file($target)) {
                    unset($manifest[$relativePath]);
                    continue;
                }

                // Either witness will do: the manifest says what was last delivered here, the declared hashes say what was ever delivered anywhere - and only the second one reaches a file withdrawn before the manifest existed, which the site would otherwise carry forever
                $targetHash = hash_file('sha256', $target);
                $pristine = \in_array($targetHash, (array) $hashes, true) || $targetHash === ($manifest[$relativePath] ?? null);

                if (!$pristine && !$force) {
                    $obsolete[$relativePath] = $bundle;
                    continue;
                }

                if (!$dryRun) {
                    // Same rule as an overwrite: what the site wrote is moved to existingFiles/ rather than lost, what it never touched is simply gone
                    $pristine ? unlink($target) : $this->backup($relativePath, $target);
                }

                if (!$pristine) {
                    ++$backedUp;
                }

                unset($manifest[$relativePath]);
                $deleted[] = $relativePath;
            }
        }

        if (!$dryRun) {
            $this->writeManifest($manifest);
            $this->ensureGitignored();
        }

        return [
            'copied' => $copied,
            'backedUp' => $backedUp,
            'skipped' => $skipped,
            'files' => $files,
            'diverged' => $diverged,
            'deleted' => $deleted,
            'obsolete' => $obsolete,
            'unmatched' => array_values(array_diff($paths, $matchedPaths)),
        ];
    }

    // The paths one bundle declares as withdrawn, each mapped to the hashes of the versions it delivered - a bundle shipping no such file simply withdraws nothing
    private function readRemoved(string $bundleDir): array
    {
        $file = $bundleDir . '/' . self::REMOVED_FILE;
        if (!is_file($file)) {
            return [];
        }

        $removed = json_decode((string) file_get_contents($file), true);

        return is_array($removed) ? $removed : [];
    }

    private function readManifest(): array
    {
        $file = $this->projectDir . '/' . self::MANIFEST_FILE;
        if (!is_file($file)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($file), true);

        return is_array($manifest) ? $manifest : [];
    }

    // Sorted, so the file a site commits only changes when a hash really did
    private function writeManifest(array $manifest): void
    {
        ksort($manifest);

        file_put_contents(
            $this->projectDir . '/' . self::MANIFEST_FILE,
            json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    // The scaffold source sits under vendor/, where an absolute path is noise in a message the developer has to read
    private function relativeToProject(string $path): string
    {
        return str_starts_with($path, $this->projectDir . '/')
            ? substr($path, \strlen($this->projectDir) + 1)
            : $path;
    }

    // A file is in scope when the path is the file itself or a directory it sits under - 'src/Scheduler' must not match 'src/SchedulerOther/...', hence the separator
    private static function matches(string $relativePath, string $path): bool
    {
        $path = trim($path, '/');

        return $relativePath === $path || str_starts_with($relativePath, $path . '/');
    }

    // Never writes to the app's own assets/styles/app.css or assets/app.js - editing a developer-owned file in place is too risky to automate reliably. Returns a one-line warning for the calling command to display when either file still imports a scaffolded assets/styles/themes/*.css by hand: with the provider installed that import now loads a stylesheet the compiled one already holds, and it is the developer's own move to remove it - without the provider it is still the only thing loading the theme, and the warning says to keep it until "c975l:scaffold:install" has run. Null when no theme file, no import, or no wiring point at all
    public function themeImportReminder(): ?string
    {
        $themes = glob($this->projectDir . '/' . self::THEMES_DIR . '/*.css') ?: [];
        if (!$themes) {
            return null;
        }

        // Nothing has to be imported by hand anymore: the scaffolded App\Service\ThemeStylesheetProvider contributes the whole themes/ directory to UiBundle's stylesheet registry, which concatenates it into the single bundles/build/site.css. A site migrated from the era when the theme was one file imported from app.js (or @import-ed from app.css) still carries that line, and it now fetches a sheet the compiled one already holds - hence a warning about what is there rather than a reminder about what is missing
        $stale = [];
        foreach ([$this->projectDir . '/assets/app.js', $this->projectDir . '/assets/styles/app.css'] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $content = (string) file_get_contents($path);
            foreach ($themes as $theme) {
                if (str_contains($content, self::THEMES_DIR_NAME . '/' . basename($theme))) {
                    $stale[] = substr($path, \strlen($this->projectDir) + 1) . ' → ' . basename($theme);
                }
            }
        }

        if (!$stale) {
            return null;
        }

        // Nothing takes that import's place until the provider is there - a run restricted by --path, or an UiBundle predating the registry, leaves it out - so the advice is reversed rather than dropped: telling a site to remove the only line loading its theme is worse than saying nothing
        if (!is_file($this->projectDir . '/' . self::THEME_PROVIDER)) {
            return sprintf('Keep the theme import for now (%s): %s is missing, so nothing else loads the theme - run "c975l:scaffold:install", then remove the import.', implode(', ', $stale), self::THEME_PROVIDER);
        }

        return sprintf('Remove the theme import, it now loads a stylesheet twice: %s. App\Service\ThemeStylesheetProvider already contributes every assets/styles/themes/*.css to the compiled bundles/build/site.css.', implode(', ', $stale));
    }

    private function backup(string $relativePath, string $target): void
    {
        $backupPath = $this->projectDir . '/existingFiles/' . $relativePath . '.old';
        if (!is_dir(\dirname($backupPath))) {
            mkdir(\dirname($backupPath), 0775, true);
        }
        rename($target, $backupPath);
    }

    private function ensureGitignored(): void
    {
        $gitignore = $this->projectDir . '/.gitignore';
        $content = is_file($gitignore) ? file_get_contents($gitignore) : '';

        $missing = array_values(array_filter(
            $this->gitignoreRules(),
            static fn (string $rule): bool => !str_contains($content, $rule)
        ));

        if ($missing) {
            file_put_contents($gitignore, rtrim($content) . "\n\n" . implode("\n", $missing) . "\n");
        }
    }

    // What the back-office writes into the project at runtime, carried between environments by the export/import (see SiteGraphicExportProvider/SiteGraphicImportProvider), never by git: tracking any of it means the next deploy resetting the working tree wipes whatever was uploaded in production. Singleton graphics sit at the root of public/ under their own role name, with the extension the role's fixed spec imposes (see c975L\UiBundle\Namer\UiMediaNamer), hence the wildcard rather than a hardcoded list of filenames. The three SEO files join them for the same reason - SeoFilesWriter rewrites them from this environment's own configs on every deployment, so a committed copy is a merge conflict waiting to happen. A rule added here doesn't untrack a file git already follows: a site that used to commit its robots.txt runs "git rm --cached public/robots.txt" once, see the readme. rclone.conf joins them for a different reason - it is written by an operator rather than by the back-office, but it holds the credential to the offsite copy and is anchored to the root so a vendored one elsewhere isn't caught by accident (see c975L\ConfigBundle\Management\OffsiteSynchronizer)
    private function gitignoreRules(): array
    {
        $rules = ['existingFiles/', 'public/medias', 'public/robots.txt', 'public/humans.txt', 'public/llms.txt', '/rclone.conf'];
        foreach (Media::getSingletonRoles() as $role) {
            $rules[] = 'public/' . $role . '.*';
        }

        return $rules;
    }
}
