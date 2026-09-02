<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests;

use PHPUnit\Framework\TestCase;

// Guards config/vendor-assets.json, which is the only place saying which version of a third-party library this bundle ships. Offline on purpose - whether a newer version exists upstream is a question for bin/vendor-assets.sh, which is run when the dependencies are, not at every commit. What is checked here is that the manifest and the files agree: a bumped number with the old file still in place, or a refreshed file nobody declared, is a bundle telling its readers something untrue about what it serves
class VendorAssetsTest extends TestCase
{
    public function testEveryDeclaredFileIsShipped(): void
    {
        foreach ($this->manifest() as $library) {
            foreach (array_keys($library['files']) as $path) {
                $this->assertFileExists($this->root() . '/' . $path, sprintf('"%s" declares "%s", which this bundle does not ship.', $library['name'], $path));
            }
        }
    }

    // The version in the manifest against the one the library prints in its own header - the one thing that catches a file swapped without the number moving, and a number moved without the file
    public function testEveryLibraryShipsTheVersionItDeclares(): void
    {
        foreach ($this->manifest() as $library) {
            if (null === $library['marker']) {
                continue;
            }

            $marker = str_replace('%version%', $library['version'], (string) $library['marker']);
            $found = array_any(array_keys($library['files']), fn (string $path): bool => str_contains((string) file_get_contents($this->root() . '/' . $path), $marker));

            $this->assertTrue($found, sprintf('No file of "%s" carries "%s": the shipped copy is not the version the manifest declares.', $library['name'], $marker));
        }
    }

    // Not every project prints a truthful version in its own banner: @hotwired/stimulus 3.2.2 still ships one reading "Stimulus 3.2.1", so no marker could prove which release is on disk. Those declare an "sha256" instead, and the guarantee is the same chain read the other way round - the url in "files" pins the version fetched, the digest pins the bytes it returned, so a file edited or swapped by hand stops being the one that url served
    public function testALibraryWithoutAVersionBannerIsPinnedByItsDigest(): void
    {
        foreach ($this->manifest() as $library) {
            if (null !== $library['marker']) {
                $this->assertNull($library['sha256'], sprintf('"%s" carries both a marker and a digest, so nothing says which one is authoritative.', $library['name']));

                continue;
            }

            $this->assertNotNull($library['sha256'], sprintf('"%s" prints no version and pins no digest, so nothing at all says which release is on disk.', $library['name']));
            $this->assertSame($library['sha256'], hash_file('sha256', $this->root() . '/' . array_key_first($library['files'])), sprintf('The shipped "%s" is not the file its source url returned.', $library['name']));
        }
    }

    // A fixture the tests drive is never something a site receives, and public/ is the one place a site is served from - a fixture landing there would be a library shipped to every site by accident. It does ship, though: Testing/JsCase reads it, and it lives beside that class in src/ so the bundles depending on this one can run their own javascript through the same harness
    public function testATestFixtureIsNeverServedToSites(): void
    {
        foreach ($this->manifest() as $library) {
            $this->assertContains($library['scope'], ['runtime', 'tests'], sprintf('"%s" declares an unknown scope.', $library['name']));

            foreach (array_keys($library['files']) as $path) {
                'tests' === $library['scope']
                    ? $this->assertStringStartsNotWith('public/', $path, sprintf('"%s" only ever serves the tests, and "%s" is under public/, which every site is served from.', $library['name'], $path))
                    : $this->assertStringStartsWith('public/', $path, sprintf('"%s" is served to sites but ships "%s" outside public/.', $library['name'], $path));
            }
        }
    }

    // What this bundle's own code calls on each library. A version bump is the one moment an upstream rename reaches a page, and nothing else here would notice: the version check above passes on any file carrying the new number, working or not
    // Names, not behaviour - proving a map really draws takes a browser and a tile server, which is not what a commit can afford. What this catches is the cheap half and the one that actually happens: a public method gone or renamed between two releases
    public function testEveryLibraryStillCarriesTheApiThisBundleCalls(): void
    {
        foreach ($this->manifest() as $library) {
            $shipped = '';

            foreach (array_keys($library['files']) as $path) {
                if (str_ends_with($path, '.js')) {
                    $shipped .= file_get_contents($this->root() . '/' . $path);
                }
            }

            foreach ($library['api'] as $symbol) {
                $this->assertStringContainsString($symbol, $shipped, sprintf('"%s" no longer carries "%s", which this bundle calls on it - the shipped version is not one the code can drive.', $library['name'], $symbol));
            }
        }
    }

    // The url a file is fetched back from, so bin/vendor-assets.sh has one source of truth to read rather than a list of its own
    public function testEveryFileCarriesTheSourceItIsFetchedFrom(): void
    {
        foreach ($this->manifest() as $library) {
            foreach ($library['files'] as $path => $url) {
                $this->assertStringStartsWith('https://', $url, sprintf('"%s" is fetched over something other than https.', $path));
                $this->assertStringContainsString('%version%', $url, sprintf('The source of "%s" pins no version, so it would silently fetch a different one.', $path));
            }
        }
    }

    // Every entry carries what the script and the readme read off it
    public function testEveryEntryCarriesTheExpectedKeys(): void
    {
        foreach ($this->manifest() as $library) {
            foreach (['name', 'version', 'scope', 'license', 'home', 'marker', 'sha256', 'api', 'files'] as $key) {
                $this->assertArrayHasKey($key, $library, sprintf('A vendored library misses the "%s" key', $key));
            }

            $this->assertNotSame([], $library['files']);
            $this->assertNotSame([], $library['api'], sprintf('"%s" declares no api at all, so the guard above would pass whatever it ships.', $library['name']));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manifest(): array
    {
        $manifest = json_decode((string) file_get_contents($this->root() . '/config/vendor-assets.json'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertNotSame([], $manifest);

        return $manifest;
    }

    private function root(): string
    {
        return \dirname(__DIR__);
    }
}
