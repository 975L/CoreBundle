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

// Locates every configs*.json declaring config entries - the single source of truth for what a config entry is, shared by c975l:config:load-all (what to load) and c975l:config:prune (what is no longer declared)
class ConfigDeclarationLocator
{
    public function __construct(
        private readonly BundleLocator $bundleLocator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Returns every declaration file: the registered c975L bundles' ones, plus the consuming application's own config/configs*.json
    public function findFiles(): array
    {
        $files = glob($this->projectDir . '/config/configs*.json') ?: [];

        foreach ($this->bundleLocator->subdirectories('config') as $directory) {
            $files = array_merge($files, glob($directory . '/configs*.json') ?: []);
        }

        sort($files);

        return $files;
    }

    // Returns every slug declared across those files, a malformed file being ignored rather than fatal
    public function findDeclaredSlugs(): array
    {
        return $this->slugsIn($this->findFiles());
    }

    // Returns the slugs declared by a c975L bundle Composer installed but bundles.php does not register. Invisible to findFiles() on purpose - a bundle the application does not run has no business creating rows in its database - but a caller deleting undeclared entries has to know about them: those rows are not orphans, their bundle only needs enabling (see ConfigPruneCommand)
    public function findUnregisteredSlugs(): array
    {
        $files = [];

        foreach ($this->bundleLocator->unregisteredDirectories() as $directory) {
            $files = array_merge($files, glob($directory . '/config/configs*.json') ?: []);
        }

        return array_values(array_diff($this->slugsIn($files), $this->findDeclaredSlugs()));
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function slugsIn(array $files): array
    {
        $slugs = [];

        foreach ($files as $file) {
            $configs = json_decode((string) file_get_contents($file), true);

            if (!is_array($configs)) {
                continue;
            }

            foreach ($configs as $config) {
                if (isset($config['slug'])) {
                    $slugs[] = $config['slug'];
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    // Returns the declaration files that exist but can't be parsed - their slugs are missing from findDeclaredSlugs(), so any caller deleting undeclared entries (c975l:config:prune) must refuse to run rather than take them for orphans
    public function findUnreadableFiles(): array
    {
        $unreadable = [];

        foreach ($this->findFiles() as $file) {
            if (!is_array(json_decode((string) file_get_contents($file), true))) {
                $unreadable[] = $file;
            }
        }

        return $unreadable;
    }

    // Returns a short readable name for a declaration file: the c975L bundle it belongs to, or "app" for the application's own file
    public function describe(string $file): string
    {
        return str_starts_with($file, $this->projectDir . '/config/')
            ? 'app (' . basename($file) . ')'
            : basename(dirname($file, 2));
    }
}
