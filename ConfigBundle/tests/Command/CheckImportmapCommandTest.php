<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\CheckImportmapCommand;
use c975L\ConfigBundle\Management\ImportmapProviderInterface;
use c975L\ConfigBundle\Management\ImportmapRegistry;
use c975L\ConfigBundle\Service\BundleLocator;
use c975L\ConfigBundle\Service\ImportmapSpecifierLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class CheckImportmapCommandTest extends TestCase
{
    private string $importmapFile;

    private string $projectDir;

    // importmap.php sits at the project root, which is what a relative entry path is relative to - the command reads it through the config reader, so the two must not be told apart here either
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/check-importmap-project-' . uniqid();
        $this->importmapFile = $this->projectDir . '/importmap.php';
        (new Filesystem())->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createProvider(array $adminEntries): ImportmapProviderInterface
    {
        $provider = $this->createStub(ImportmapProviderInterface::class);
        $provider->method('getAdminImportmapEntries')->willReturn($adminEntries);
        $provider->method('getImportmapEntries')->willReturn([]);

        return $provider;
    }

    // The bundles the fabricated vendor/c975l/* directories stand for, as the kernel would report them
    private function bundleLocator(): BundleLocator
    {
        $metadata = [];
        foreach (glob($this->projectDir . '/vendor/c975l/*', \GLOB_ONLYDIR) ?: [] as $directory) {
            $metadata[basename($directory)] = ['path' => $directory, 'namespace' => 'c975L\\Test'];
        }

        return new BundleLocator($metadata);
    }

    // The directories a Symfony application maps: its own assets/, plus the assets/ of every installed package, a bundle being free to sit a directory deeper (c975l/core-bundle). Anything else on disk is unreachable for AssetMapper, which is the whole point of what the command checks. Not in debug mode: a fabricated project doesn't have them all, and a missing one is to be skipped rather than raised
    private function assetMapperRepository(): AssetMapperRepository
    {
        $paths = [$this->projectDir . '/assets' => ''];

        foreach (array_merge(glob($this->projectDir . '/vendor/*/*/assets') ?: [], glob($this->projectDir . '/vendor/*/*/*/assets') ?: []) as $directory) {
            $paths[$directory] = '';
        }

        return new AssetMapperRepository($paths, $this->projectDir, [], true, false);
    }

    private function createTester(array $providers): CommandTester
    {
        $configReader = new ImportMapConfigReader($this->importmapFile, new RemotePackageStorage(sys_get_temp_dir()));

        return new CommandTester(new CheckImportmapCommand(
            new ImportmapRegistry($providers, new BundleLocator([]), $this->projectDir),
            new ImportmapSpecifierLocator($this->bundleLocator(), $this->projectDir),
            $configReader,
            $this->assetMapperRepository(),
            $this->projectDir,
        ));
    }

    private function writeControllersJson(bool $chartjsEnabled): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/assets/controllers.json', json_encode([
            'controllers' => [
                '@symfony/ux-chartjs' => ['chart' => ['enabled' => $chartjsEnabled, 'fetch' => 'eager']],
            ],
            'entrypoints' => [],
        ]));
    }

    public function testExecuteAddsMissingEntryToEmptyImportmap(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");

        $provider = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);
        $tester = $this->createTester([$provider]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 entry(ies) added', $tester->getDisplay());

        $written = require $this->importmapFile;
        $this->assertSame('./vendor/c975l/config-bundle/assets/controllers-admin.js', $written['@c975l/config-bundle/controllers-admin.js']['path']);
        $this->assertTrue($written['@c975l/config-bundle/controllers-admin.js']['entrypoint']);
    }

    // An override is a deliberate choice as long as what it points at is really servable, so the provider's own path never wins over it
    public function testExecuteNeverTouchesAnOverrideThatStillResolves(): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/assets/a-custom-override-path.js', '');
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                '@c975l/config-bundle/controllers-admin.js' => ['path' => './assets/a-custom-override-path.js', 'entrypoint' => false],
            ];

            PHP);

        $provider = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);
        $tester = $this->createTester([$provider]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('already up to date', $tester->getDisplay());

        $written = require $this->importmapFile;
        $this->assertSame('./assets/a-custom-override-path.js', $written['@c975l/config-bundle/controllers-admin.js']['path']);
    }

    // What merging ConfigBundle and UiBundle into c975l/core-bundle did to every path under vendor/: the entry is there, its file is not, and only the provider knows where the bundle went
    public function testExecuteRepointsAnEntryWhoseFileIsGone(): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/vendor/c975l/core-bundle/ConfigBundle/assets/controllers-admin.js', '');
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
            ];

            PHP);

        $provider = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/core-bundle/ConfigBundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);
        $tester = $this->createTester([$provider]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 entry(ies) repointed', $tester->getDisplay());

        $written = require $this->importmapFile;
        $this->assertSame('./vendor/c975l/core-bundle/ConfigBundle/assets/controllers-admin.js', $written['@c975l/config-bundle/controllers-admin.js']['path']);
    }

    // An override may be written as a plain filesystem path, which the importmap reads as-is rather than relative to the project root - spelled out by hand, its leading slash was stripped and the file was looked for under that root instead
    public function testExecuteNeverTouchesAnOverrideWrittenAsAnAbsolutePath(): void
    {
        $override = $this->projectDir . '/assets/shared/a-custom-override-path.js';
        (new Filesystem())->dumpFile($override, '');
        (new Filesystem())->dumpFile($this->importmapFile, <<<PHP
            <?php

            return [
                '@c975l/config-bundle/controllers-admin.js' => ['path' => '{$override}', 'entrypoint' => false],
            ];

            PHP);

        $provider = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);
        $tester = $this->createTester([$provider]);
        $tester->execute([]);

        $this->assertStringContainsString('already up to date', $tester->getDisplay());
        $this->assertStringNotContainsString("out of AssetMapper's reach", $tester->getDisplay());

        $written = require $this->importmapFile;
        $this->assertSame($override, $written['@c975l/config-bundle/controllers-admin.js']['path']);
    }

    // Same for a path climbing out of a subdirectory: only the reader's own resolution reads it the way AssetMapper will
    public function testExecuteNeverTouchesAnOverrideReachingUpADirectory(): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/assets/a-custom-override-path.js', '');
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                '@c975l/config-bundle/controllers-admin.js' => ['path' => './assets/js/../a-custom-override-path.js', 'entrypoint' => false],
            ];

            PHP);

        $provider = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);
        $tester = $this->createTester([$provider]);
        $tester->execute([]);

        $this->assertStringContainsString('already up to date', $tester->getDisplay());
        $this->assertStringNotContainsString("out of AssetMapper's reach", $tester->getDisplay());
    }

    // The working copy's own trap: a c975L bundle symlinked into vendor/ for development resolves to its repository, and the path written then keeps pointing there once Composer puts the real package back. The file is still on disk, so "does it exist" saw nothing wrong, and the entry brought every page loading it down until it was fixed by hand
    public function testExecuteRepointsAnEntryPointingOutsideTheMappedPaths(): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/vendor/c975l/core-bundle/UiBundle/assets/js/pointer-sort.js', '');

        // Stands for the bundle's own repository, where the symlink used to lead: a real file, mapped by nothing
        (new Filesystem())->dumpFile($this->projectDir . '/dev-checkout/UiBundle/assets/js/pointer-sort.js', '');
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                '@c975l/ui-bundle/pointer-sort.js' => ['path' => './dev-checkout/UiBundle/assets/js/pointer-sort.js'],
            ];

            PHP);

        $provider = $this->createProvider([
            '@c975l/ui-bundle/pointer-sort.js' => ['path' => './vendor/c975l/core-bundle/UiBundle/assets/js/pointer-sort.js'],
        ]);
        $tester = $this->createTester([$provider]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 entry(ies) repointed', $tester->getDisplay());

        $written = require $this->importmapFile;
        $this->assertSame('./vendor/c975l/core-bundle/UiBundle/assets/js/pointer-sort.js', $written['@c975l/ui-bundle/pointer-sort.js']['path']);
    }

    // Same path, but claimed by no provider: nothing can repair it, so it is at least reported rather than left to surface as a 500
    public function testExecuteWarnsAboutAPathOutsideTheMappedPathsNoProviderClaims(): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/dev-checkout/some-asset.js', '');
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                'app' => ['path' => './dev-checkout/some-asset.js', 'entrypoint' => true],
            ];

            PHP);

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString("out of AssetMapper's reach", $tester->getDisplay());
        $this->assertStringContainsString('./dev-checkout/some-asset.js', $tester->getDisplay());
    }

    // A dead path no provider claims can't be repaired, only reported - it answers 500 on the first page rendering it, and nothing else says so
    public function testExecuteWarnsAboutADeadPathNoProviderClaims(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                'app' => ['path' => './assets/gone.js', 'entrypoint' => true],
            ];

            PHP);

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString("out of AssetMapper's reach", $tester->getDisplay());
        $this->assertStringContainsString('./assets/gone.js', $tester->getDisplay());
    }

    public function testExecuteIsIdempotentAcrossTwoRuns(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");

        $provider = $this->createProvider([
            '@c975l/config-bundle/controllers-admin.js' => ['path' => './vendor/c975l/config-bundle/assets/controllers-admin.js', 'entrypoint' => true],
        ]);

        $this->createTester([$provider])->execute([]);
        $secondTester = $this->createTester([$provider]);
        $secondTester->execute([]);

        $this->assertStringContainsString('already up to date', $secondTester->getDisplay());
    }

    public function testExecuteWarnsWhenControllersJsonEnablesChartjs(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        $this->writeControllersJson(true);

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('@symfony/ux-chartjs', $tester->getDisplay());
    }

    public function testExecuteDoesNotWarnWhenControllersJsonDisablesChartjs(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        $this->writeControllersJson(false);

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertStringNotContainsString('@symfony/ux-chartjs', $tester->getDisplay());
    }

    public function testExecuteDoesNotWarnWhenControllersJsonIsMissing(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertStringNotContainsString('@symfony/ux-chartjs', $tester->getDisplay());
    }

    // An unreadable controllers.json is the app's own problem to report, not this command's job to fail on
    public function testExecuteStillSucceedsOnMalformedControllersJson(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        (new Filesystem())->dumpFile($this->projectDir . '/assets/controllers.json', '{ not json');

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('@symfony/ux-chartjs', $tester->getDisplay());
    }

    // The entry sits under a "controllers" key - a flat file (or one enabling something else entirely) must not trigger the warning
    public function testExecuteDoesNotWarnWhenChartjsIsNotUnderTheControllersKey(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        (new Filesystem())->dumpFile($this->projectDir . '/assets/controllers.json', json_encode([
            '@symfony/ux-chartjs' => ['chart' => ['enabled' => true]],
        ]));

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertStringNotContainsString('@symfony/ux-chartjs', $tester->getDisplay());
    }

    // Mimics an installed c975L bundle whose own JS imports a bare specifier
    private function writeBundleAsset(string $contents): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/vendor/c975l/config-bundle/assets/controllers-admin.js', $contents);
    }

    // Mimics a Symfony UX package as Composer installs it: an assets/package.json naming the specifier and pointing at its entry file
    private function writeUxPackage(string $vendor, string $name, string $specifier, string $main = 'dist/controller.js'): void
    {
        (new Filesystem())->dumpFile(
            $this->projectDir . '/vendor/' . $vendor . '/' . $name . '/assets/package.json',
            json_encode(['name' => $specifier, 'main' => $main])
        );
    }

    // The failure this whole check exists for: a bundle's JS imports a package by bare specifier, no importmap entry declares it, and the browser then fails the entire module - every Stimulus controller it registers silently lost
    public function testExecuteAddsAnEntryForABareSpecifierImportedByABundleAsset(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        $this->writeBundleAsset("import ChartjsController from '@symfony/ux-chartjs';\n");
        $this->writeUxPackage('symfony', 'ux-chartjs', '@symfony/ux-chartjs');

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $written = require $this->importmapFile;
        $this->assertSame('./vendor/symfony/ux-chartjs/assets/dist/controller.js', $written['@symfony/ux-chartjs']['path']);
        // Imported by another module, never loaded on its own
        $this->assertArrayNotHasKey('entrypoint', $written['@symfony/ux-chartjs']);
    }

    // Relative imports are resolved by AssetMapper from the importing file itself and never need an entry of their own
    public function testExecuteIgnoresRelativeImports(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        $this->writeBundleAsset("import { addToolbarButton } from './js/block-toolbar.js';\nimport '../styles/app.css';\n");

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertStringContainsString('already up to date', $tester->getDisplay());
    }

    // An entry an app deliberately points elsewhere (a fork, a CDN version) must survive untouched
    public function testExecuteLeavesABareSpecifierAlreadyDeclaredAlone(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, <<<'PHP'
            <?php

            return [
                '@symfony/ux-chartjs' => ['path' => './assets/my-own-fork.js'],
            ];

            PHP);
        $this->writeBundleAsset("import ChartjsController from '@symfony/ux-chartjs';\n");
        $this->writeUxPackage('symfony', 'ux-chartjs', '@symfony/ux-chartjs');

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertStringContainsString('already up to date', $tester->getDisplay());
        $written = require $this->importmapFile;
        $this->assertSame('./assets/my-own-fork.js', $written['@symfony/ux-chartjs']['path']);
    }

    // Nothing to resolve it against: reported rather than guessed at, so the missing package is visible from the CLI instead of only in the browser console
    public function testExecuteWarnsWhenABareSpecifierCannotBeResolvedInVendor(): void
    {
        (new Filesystem())->dumpFile($this->importmapFile, "<?php\n\nreturn [];\n");
        $this->writeBundleAsset("import Thing from '@acme/not-installed';\n");

        $tester = $this->createTester([]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('@acme/not-installed', $tester->getDisplay());
    }
}
