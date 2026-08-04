<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Translation;

use PHPUnit\Framework\TestCase;

// A key the bundle ships must be asked for in the catalogue that ships it: asked from another domain - "site", owned by SiteBundle, is the easy mistake - the translator finds nothing and the page displays the raw key
class TranslationDomainTest extends TestCase
{
    // The catalogues the bundle ships, keyed by the domain their name declares
    private const DOMAINS = ['config', 'site_config', 'validators'];

    // The two ways a domain is named next to its key in "src/"
    private const PATTERNS = [
        // $this->translator->trans('label.x', [...], 'config') - the tempered dot keeps a call missing its domain from reaching into the next one, and the trailing comma is the multi-line form's
        '/->trans\(\s*\'([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)\'(?:(?!->trans\().)*?,\s*\'([a-zA-Z_]+)\'\s*,?\s*\)/s',
        // HealthCheckErrorRow::build($this->translator, 'config', $url, $label, 'label.x', ...)
        '/HealthCheckErrorRow::build\(\s*[^,]+,\s*\'([a-zA-Z_]+)\'\s*,[^,]+,[^,]+,\s*\'([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)\'/',
    ];

    public function testEveryKeyIsAskedFromTheCatalogueThatShipsIt(): void
    {
        $catalogue = $this->catalogue();
        $usages = $this->usages();
        $this->assertNotEmpty($usages, 'No translated key found in "src/", the test itself is broken.');

        foreach ($usages as [$file, $key, $domain]) {
            // Keys owned by another bundle (SiteBundle's "site", VerifyEmailBundle's own) are not ours to place
            if (!isset($catalogue[$key])) {
                continue;
            }

            $this->assertSame($catalogue[$key], $domain, sprintf('"%s" asks for "%s" in the "%s" domain, while the bundle ships it in "%s".', $file, $key, $domain, $catalogue[$key]));
        }
    }

    // Every key the bundle ships, mapped to the domain it is shipped in
    private function catalogue(): array
    {
        $catalogue = [];

        foreach (self::DOMAINS as $domain) {
            $path = $this->root() . '/translations/' . $domain . '.en.xlf';
            $this->assertFileExists($path);
            $xliff = simplexml_load_file($path);

            foreach ($xliff->file->body->{'trans-unit'} as $unit) {
                $catalogue[(string) $unit->source] = $domain;
            }
        }

        return $catalogue;
    }

    // Collects [file, key, domain] for every call naming both, in the bundle's own sources and in the scaffold copied into the consuming app
    private function usages(): array
    {
        $usages = [];

        foreach ($this->phpFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $name = substr($file, strlen($this->root()) + 1);

            // trans() names the key first, build() the domain first
            foreach (self::PATTERNS as $index => $pattern) {
                preg_match_all($pattern, $contents, $matches);

                foreach ($matches[1] as $position => $first) {
                    $usages[] = 0 === $index ? [$name, $first, $matches[2][$position]] : [$name, $matches[2][$position], $first];
                }
            }
        }

        return $usages;
    }

    private function phpFiles(): array
    {
        $files = [];

        foreach (['src', 'scaffold/src'] as $tree) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/' . $tree));

            foreach ($iterator as $file) {
                if ('php' === $file->getExtension()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
