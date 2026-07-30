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

// Constraint messages are translated in the "validators" domain, not "ui": a key left in the wrong catalogue shows the editor its raw name
class ConstraintMessageCatalogueTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'es'];

    public static function localeProvider(): array
    {
        return array_map(static fn (string $locale): array => [$locale], self::LOCALES);
    }

    #[DataProvider('localeProvider')]
    public function testEveryConstraintMessageIsDeclaredInTheValidatorsCatalogue(string $locale): void
    {
        $sources = $this->catalogueSources(sprintf('validators.%s.xlf', $locale));
        $messages = $this->constraintMessages();
        $this->assertNotEmpty($messages, 'No constraint message found in "src/", the test itself is broken.');

        foreach ($messages as $file => $keys) {
            foreach ($keys as $key) {
                $this->assertContains($key, $sources, sprintf('"%s" uses the constraint message "%s", missing from "validators.%s.xlf".', $file, $key, $locale));
            }
        }
    }

    // Collects the message keys passed to a constraint as a named argument, per file
    private function constraintMessages(): array
    {
        $messages = [];

        foreach ($this->phpFiles() as $file) {
            preg_match_all("/[a-zA-Z]*[Mm]essage: '([a-z][a-zA-Z0-9_.]+)'/", (string) file_get_contents($file), $matches);

            if ($matches[1]) {
                $messages[substr($file, strlen($this->root()) + 1)] = array_unique($matches[1]);
            }
        }

        return $messages;
    }

    private function phpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/src'));

        foreach ($iterator as $file) {
            if ('php' === $file->getExtension()) {
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

    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
