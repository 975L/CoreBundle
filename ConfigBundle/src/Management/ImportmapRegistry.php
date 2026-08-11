<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\BundleLocator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

// Merges the importmap entries contributed by every ImportmapProviderInterface (see readme), each path resolved against the directory of the bundle that declared it
class ImportmapRegistry
{
    private readonly array $entries;

    // @param iterable<ImportmapProviderInterface> $providers
    public function __construct(
        iterable $providers,
        private readonly BundleLocator $bundleLocator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $entries = [];

        foreach ($providers as $provider) {
            $prefix = $this->prefixFor($provider::class);

            foreach (array_merge($provider->getAdminImportmapEntries(), $provider->getImportmapEntries()) as $name => $entry) {
                $entry['path'] = $prefix . $entry['path'];
                $entries[$name] = $entry;
            }
        }

        $this->entries = $entries;
    }

    public function all(): array
    {
        return $this->entries;
    }

    // Where the declaring bundle sits, seen from the project root - so no bundle has to spell out its own place under vendor/, which is exactly what the ConfigBundle+UiBundle merge invalidated. No prefix for a provider the application ships itself: its paths are already the project root's own
    private function prefixFor(string $providerClass): string
    {
        $directory = $this->bundleLocator->directoryForClass($providerClass);

        return null === $directory ? '' : './' . Path::makeRelative($directory, $this->projectDir) . '/';
    }
}
