<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\ImportmapRegistry;
use c975L\ConfigBundle\Service\ImportmapSpecifierLocator;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'c975l:config:check-importmap',
    description: 'Adds any importmap.php entry missing from the app - the ones contributed by an ImportmapProviderInterface (see readme), plus the third-party packages the c975L bundles\' own JS imports. Safe to run on every composer update, never touches an entry already present'
)]
class CheckImportmapCommand extends Command
{
    public function __construct(
        private readonly ImportmapRegistry $importmapRegistry,
        private readonly ImportmapSpecifierLocator $specifierLocator,
        #[Autowire(service: 'asset_mapper.importmap.config_reader')]
        private readonly ImportMapConfigReader $configReader,
        #[Autowire(service: 'asset_mapper.repository')]
        private readonly AssetMapperRepository $assetMapperRepository,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->warnOnEagerChartjs($io);
        $entries = $this->configReader->getEntries();

        $added = [];
        $repaired = [];
        foreach ($this->importmapRegistry->all() as $importName => $data) {
            $entry = ImportMapEntry::createLocal(
                $importName,
                ImportMapType::from($data['type'] ?? 'js'),
                $data['path'],
                $data['entrypoint'] ?? false,
            );

            if (!$entries->has($importName)) {
                $entries->add($entry);
                $added[] = $importName;

                continue;
            }

            // An entry already there but pointing where AssetMapper can't serve from, the provider having moved with its bundle - which is what merging ConfigBundle and UiBundle into c975l/core-bundle did to every path under vendor/. The registry resolves the declaring bundle's real directory, so it is the authority here; the guard on isServable keeps a deliberate override in place as long as it still resolves
            $current = $entries->get($importName);
            if ($current->path !== $data['path'] && !$this->isServable($current->path)) {
                $entries->add($entry);
                $repaired[] = sprintf('%s: %s → %s', $importName, $current->path, $data['path']);
            }
        }

        $added = array_merge($added, $this->addMissingBareSpecifiers($entries, $io));
        $this->warnOnDeadPaths($entries, $io);

        if (!$added && !$repaired) {
            $io->success('importmap.php is already up to date.');

            return Command::SUCCESS;
        }

        $this->configReader->writeEntries($entries);

        if ($added) {
            $io->success(sprintf('%d entry(ies) added to importmap.php:', count($added)));
            $io->listing($added);
        }

        if ($repaired) {
            $io->success(sprintf('%d entry(ies) repointed at their new path:', count($repaired)));
            $io->listing($repaired);
        }

        return Command::SUCCESS;
    }

    // Reports a local entry AssetMapper can't serve and that no provider claims, so nothing above could repair it. AssetMapper only raises it when a page actually renders that entrypoint - a 500 on the front end for what is a one-line fix in importmap.php. Remote packages are skipped: their path lives under assets/vendor/ and is restored by "importmap:install", not by this command
    private function warnOnDeadPaths(ImportMapEntries $entries, SymfonyStyle $io): void
    {
        $known = $this->importmapRegistry->all();
        $dead = [];

        foreach ($entries as $entry) {
            if ($entry->isRemotePackage() || isset($known[$entry->importName]) || $this->isServable($entry->path)) {
                continue;
            }

            $dead[] = sprintf('%s (%s)', $entry->importName, $entry->path);
        }

        if ($dead) {
            $io->warning(sprintf(
                "Declared in importmap.php but out of AssetMapper's reach - gone from disk, or sitting outside the mapped paths: %s.\nAny page loading one of them answers 500. Fix the path, or drop the entry if what it pointed at is gone.",
                implode(', ', $dead)
            ));
        }
    }

    // Whether AssetMapper can actually serve that path, which "the file is there" doesn't answer: a file outside the mapped paths exists all the same and still brings the page down. That is a working copy's normal state, a c975L bundle symlinked into vendor/ for development resolving to its own repository - an entry written then keeps pointing there once Composer puts the real package back, and the file it names is still on disk. Includes the is_file check (see AssetMapperRepository), and resolves symlinks on both sides, so the same entry passes while the symlink is up and is repaired the moment it goes
    private function isServable(string $path): bool
    {
        // The reader is the authority on what an importmap "path" means: relative to importmap.php's own directory when it starts with a ".", already a filesystem path otherwise. Spelled out here by hand, an absolute path lost its leading slash and was looked for under the project root, so an override pointing at a file really there was reported missing and repointed
        return null !== $this->assetMapperRepository->findLogicalPath($this->configReader->convertPathToFilesystemPath($path));
    }

    // A c975L bundle's own JS may import a third-party package by bare specifier (e.g. ConfigBundle's controllers-admin.js importing "@symfony/ux-chartjs" for the health check chart). That entry is normally written by the package's own Flex recipe, which doesn't always run - and when it's missing the browser can't resolve the specifier, so the whole module fails and every Stimulus controller it registers is lost, back-office drag-and-drop included. Never an entrypoint: these are imported by another module, not loaded on their own
    private function addMissingBareSpecifiers(ImportMapEntries $entries, SymfonyStyle $io): array
    {
        $added = [];
        $unresolved = [];

        foreach ($this->specifierLocator->findBareSpecifiers() as $specifier) {
            if ($entries->has($specifier)) {
                continue;
            }

            $path = $this->specifierLocator->resolvePath($specifier);
            if (null === $path) {
                $unresolved[] = $specifier;

                continue;
            }

            $entries->add(ImportMapEntry::createLocal($specifier, ImportMapType::JS, $path, false));
            $added[] = $specifier;
        }

        if ($unresolved) {
            $io->warning(sprintf(
                "Imported by a c975L bundle's JS but missing from importmap.php, and not found in vendor/: %s.\nThe browser won't be able to resolve that specifier and the whole module importing it will fail. Install the package, or add the entry by hand.",
                implode(', ', $unresolved)
            ));
        }

        return $added;
    }

    // symfony/ux-chartjs' Flex recipe enables its chart controller eagerly in assets/controllers.json, which makes startStimulusApp() import chart.js on every front-end page and makes every admin Stimulus app register the controller a second time (see readme). Only warns - rewriting the app's controllers.json isn't this command's job
    private function warnOnEagerChartjs(SymfonyStyle $io): void
    {
        $file = $this->projectDir . '/assets/controllers.json';
        if (!is_file($file)) {
            return;
        }

        $config = json_decode((string) file_get_contents($file), true);
        if (!is_array($config) || true !== ($config['controllers']['@symfony/ux-chartjs']['chart']['enabled'] ?? false)) {
            return;
        }

        $io->warning('assets/controllers.json enables "@symfony/ux-chartjs": chart.js is loaded on every page of the site and registered several times on the dashboard. Set "enabled" to false - ConfigBundle registers that controller itself (see readme).');
    }
}
