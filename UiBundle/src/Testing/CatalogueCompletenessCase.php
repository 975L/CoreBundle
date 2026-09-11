<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Testing;

use PHPUnit\Framework\TestCase;

/**
 * The promise this ecosystem makes about languages: adding one is adding its translation file, and nothing else.
 *
 * That only holds while every file of a domain says the very same things. A key written in one language and missing
 * from another is a screen that falls back to the source words in the middle of a translated page - and Symfony's
 * fallback makes it silent, which is what lets it live for months.
 *
 * So: every language file of a domain carries exactly the keys of the language the bundle is written in, none of them
 * empty, and none of them holding a key the source does not. Three failures, three different mistakes: a key added
 * without its translations, a translation left blank, a key renamed on one side only.
 *
 * Lives in src/ rather than tests/ for the same reason as JsCase beside it: a bundle's tests/ is autoload-dev and
 * never reaches the bundles that depend on it. A satellite extends this and points translationsDirectory() at itself.
 *
 * Never registered as a service (see the Testing/ exclusion in config/services.yaml).
 */
abstract class CatalogueCompletenessCase extends TestCase
{
    // The language a c975L bundle is written in, and the one every other file of a domain is measured against
    protected const string SOURCE_LOCALE = 'fr';

    abstract protected static function translationsDirectory(): string;

    public function testEveryLanguageOfADomainSaysTheSameThings(): void
    {
        $domains = $this->domains();

        $this->assertNotSame([], $domains, sprintf('No translation file found in "%s".', static::translationsDirectory()));

        foreach ($domains as $domain => $files) {
            $source = $files[static::SOURCE_LOCALE] ?? null;

            $this->assertNotNull($source, sprintf('Domain "%s" has no "%s" file, which is the one every other is measured against.', $domain, static::SOURCE_LOCALE));

            $expected = $this->units($source);

            foreach ($files as $locale => $file) {
                if (static::SOURCE_LOCALE === $locale) {
                    continue;
                }

                $units = $this->units($file);

                $missing = array_keys(array_diff_key($expected, $units));
                $this->assertSame([], $missing, sprintf('"%s.%s" is missing %d key(s) of the %s file: %s', $domain, $locale, \count($missing), static::SOURCE_LOCALE, implode(', ', \array_slice($missing, 0, 5))));

                $extra = array_keys(array_diff_key($units, $expected));
                $this->assertSame([], $extra, sprintf('"%s.%s" holds %d key(s) the %s file does not: %s', $domain, $locale, \count($extra), static::SOURCE_LOCALE, implode(', ', \array_slice($extra, 0, 5))));

                $empty = array_keys(array_filter($units, static fn (string $value): bool => '' === trim($value)));
                $this->assertSame([], $empty, sprintf('"%s.%s" leaves %d key(s) blank: %s', $domain, $locale, \count($empty), implode(', ', \array_slice($empty, 0, 5))));
            }
        }
    }

    /**
     * The files this bundle ships, gathered by domain: "shop.fr.xlf" and "shop.es.xlf" are one domain in two languages.
     *
     * @return array<string, array<string, string>> domain => locale => path
     */
    private function domains(): array
    {
        $domains = [];

        foreach (glob(rtrim(static::translationsDirectory(), '/') . '/*.xlf') ?: [] as $file) {
            if (1 === preg_match('/^(?<domain>.+)\.(?<locale>[a-z]{2}(?:_[A-Z]{2})?)\.xlf$/', basename($file), $matches)) {
                $domains[$matches['domain']][$matches['locale']] = $file;
            }
        }

        ksort($domains);

        return $domains;
    }

    /**
     * What one file says, keyed by what it says it about.
     *
     * Read with SimpleXML rather than Symfony's own loader: a loader drops an empty target silently, and a blank
     * translation is one of the three things this is here to catch.
     *
     * @return array<string, string> key => the words that language holds
     */
    private function units(string $file): array
    {
        $xml = simplexml_load_file($file);

        $this->assertNotFalse($xml, sprintf('"%s" is not readable as XML.', basename($file)));

        $units = [];

        foreach ($xml->file->body->{'trans-unit'} ?? [] as $unit) {
            $key = trim((string) $unit->source);
            if ('' !== $key) {
                $units[$key] = (string) $unit->target;
            }
        }

        $this->assertNotSame([], $units, sprintf('"%s" holds no trans-unit at all.', basename($file)));

        return $units;
    }
}
