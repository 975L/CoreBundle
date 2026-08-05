<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// A template asking for a key the bundle doesn't ship renders that key's raw name to the visitor - which is what pointing a component at the consuming app's "site" domain did for years
class TemplateDomainCatalogueTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'es'];

    // The two the bundle ships. Anything else - "site" above all - belongs to the app, which owes this bundle nothing
    private const OWN_DOMAINS = ['ui', 'validators'];

    // Domains of the packages composer.json requires: their catalogue is installed wherever this bundle is, so a template overriding one of their blocks may keep their wording
    private const REQUIRED_DOMAINS = ['EasyAdminBundle'];

    // The bundle translates in its own domains only: a component rendered on a site that never declared the key shows its raw name instead of a sentence
    public function testNoTemplateTranslatesInAnotherPackagesDomain(): void
    {
        foreach ($this->twigFiles() as $file) {
            $contents = (string) file_get_contents($file);
            preg_match_all('/\|\s*trans\(\s*(?:\{[^}]*\}|\[[^\]]*\])?\s*,\s*[\'"]([a-zA-Z]+)[\'"]/', $contents, $explicit);
            preg_match_all('/trans_default_domain\s+[\'"]([a-zA-Z]+)[\'"]/', $contents, $default);

            foreach (array_merge($explicit[1], $default[1]) as $domain) {
                $this->assertContains(
                    $domain,
                    array_merge(self::OWN_DOMAINS, self::REQUIRED_DOMAINS),
                    sprintf('"%s" translates in the "%s" domain, which neither this bundle nor its dependencies ship.', $this->relative($file), $domain)
                );
            }
        }
    }

    #[DataProvider('localeProvider')]
    public function testEveryKeyATemplateAsksForIsDeclaredInTheUiCatalogue(string $locale): void
    {
        $sources = $this->catalogueSources(sprintf('ui.%s.xlf', $locale));
        $keys = $this->templateKeys();
        $this->assertNotEmpty($keys, 'No translation key found in "templates/", the test itself is broken.');

        foreach ($keys as $key => $file) {
            $this->assertContains(
                $key,
                $sources,
                sprintf('"%s" asks for "%s", missing from "translations/ui.%s.xlf".', $file, $key, $locale)
            );
        }
    }

    public static function localeProvider(): array
    {
        return array_map(static fn (string $locale): array => [$locale], self::LOCALES);
    }

    // Collects the literal keys the templates ask this bundle's own catalogue for, each mapped to the first file asking for it. A key
    // built from a variable ("'label.' ~ kind"|trans) has no literal to read and is left to the runtime, and one named with a
    // dependency's domain is that dependency's to ship
    private function templateKeys(): array
    {
        $keys = [];

        foreach ($this->twigFiles() as $file) {
            preg_match_all('/[\'"]([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)[\'"]\s*\|\s*trans(?:\(\s*(?:\{[^}]*\}|\[[^\]]*\])?\s*(?:,\s*[\'"]([a-zA-Z]+)[\'"])?)?/', (string) file_get_contents($file), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $domain = $match[2] ?? '';

                if ('' !== $domain && 'ui' !== $domain) {
                    continue;
                }

                $keys[$match[1]] ??= $this->relative($file);
            }
        }

        return $keys;
    }

    private function twigFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/templates'));

        foreach ($iterator as $file) {
            if ('twig' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function catalogueSources(string $name): array
    {
        $path = $this->root() . '/translations/' . $name;
        $this->assertFileExists($path);

        $xliff = simplexml_load_file($path);
        $sources = [];

        foreach ($xliff->file->body->{'trans-unit'} as $unit) {
            $sources[] = (string) $unit->source;
        }

        return $sources;
    }

    private function relative(string $file): string
    {
        return substr($file, \strlen($this->root()) + 1);
    }

    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
