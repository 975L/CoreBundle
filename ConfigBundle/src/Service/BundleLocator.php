<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Where the c975L bundles this application runs actually sit on disk - what every "read something from each installed c975L bundle" service (configs.json, scaffold, assets, sources) asks instead of guessing at "vendor/c975l/<package>". A package is free to ship several bundles: c975l/core-bundle ships two, one directory deeper than that guess ever looked
class BundleLocator
{
    private const NAMESPACE_PREFIX = 'c975L\\';

    private const VENDOR_PREFIX = 'c975l/';

    /**
     * @param array<string, array{path: string, namespace: string}> $bundlesMetadata
     */
    public function __construct(
        #[Autowire(param: 'kernel.bundles_metadata')]
        private readonly array $bundlesMetadata,
    ) {
    }

    /**
     * Each registered c975L bundle's own directory, keyed by bundle name ("c975LConfigBundle" => "…/core-bundle/ConfigBundle").
     * Registered, not merely installed: a bundle absent from bundles.php contributes nothing to the application anyway.
     *
     * @return array<string, string>
     */
    public function directories(): array
    {
        $directories = [];

        foreach ($this->bundlesMetadata as $name => $metadata) {
            if (str_starts_with($metadata['namespace'], self::NAMESPACE_PREFIX)) {
                $directories[$name] = $metadata['path'];
            }
        }

        return $directories;
    }

    /**
     * The directory of the c975L bundle a class belongs to, matched on its namespace - what lets a registry
     * resolve a contribution back to the bundle that shipped it. Null for a class living anywhere else,
     * the consuming application's own services included.
     */
    public function directoryForClass(string $class): ?string
    {
        $path = null;
        $matched = '';

        foreach ($this->bundlesMetadata as $metadata) {
            // The longest match wins, two bundles being free to nest their namespaces
            if (
                str_starts_with($metadata['namespace'], self::NAMESPACE_PREFIX)
                && str_starts_with($class, $metadata['namespace'] . '\\')
                && strlen($metadata['namespace']) > strlen($matched)
            ) {
                $path = $metadata['path'];
                $matched = $metadata['namespace'];
            }
        }

        return $path;
    }

    /**
     * The directories of the c975L packages Composer installed but the application did not register in bundles.php -
     * what a destructive caller has to know about before taking their contribution for gone (see ConfigPruneCommand).
     * Read off Composer's own registry rather than off a path pattern, and widened to each package's immediate
     * subdirectories, a package being free to ship several bundles the way this one does.
     *
     * @return list<string>
     */
    public function unregisteredDirectories(): array
    {
        $registered = array_filter(array_map(realpath(...), array_values($this->directories())));
        $directories = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if (!str_starts_with($package, self::VENDOR_PREFIX)) {
                continue;
            }

            $installPath = InstalledVersions::getInstallPath($package);
            if (null === $installPath) {
                continue;
            }

            foreach ([$installPath, ...(glob($installPath . '/*', \GLOB_ONLYDIR) ?: [])] as $directory) {
                $directory = realpath($directory);
                if (false !== $directory && !in_array($directory, $registered, true)) {
                    $directories[] = $directory;
                }
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * The subdirectory of that name in each of them, skipping the bundles not shipping one.
     *
     * @return array<string, string>
     */
    public function subdirectories(string $name): array
    {
        $directories = [];

        foreach ($this->directories() as $bundle => $directory) {
            if (is_dir($directory . '/' . $name)) {
                $directories[$bundle] = $directory . '/' . $name;
            }
        }

        return $directories;
    }
}
