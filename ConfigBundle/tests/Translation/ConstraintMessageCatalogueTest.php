<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Constraint messages are translated in the "validators" domain, not "config": a key left in the wrong catalogue shows the editor its raw name
class ConstraintMessageCatalogueTest extends TestCase
{
    private const array LOCALES = ['fr', 'en', 'es'];

    // The scaffold is copied into the consuming app and carries its own catalogue, so it is checked against its own "translations/" - not the bundle's
    private const array TREES = ['' => '', 'scaffold' => 'scaffold/'];

    // Every way a message key reaches the validator - a named argument alone misses the three others
    private const array PATTERNS = [
        // new IsTrue(message: 'text.checkbox_required')
        '/[a-zA-Z]*[Mm]essage: \'([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)\'/',
        // public string $message = 'text.captcha_failed';
        '/\$[a-zA-Z]*[Mm]essage = \'([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)\'/',
        // 'invalid_message' => 'text.password_mismatch'
        '/\'[a-z_]*message\' => \'([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)\'/',
        // $context->buildViolation('label.invalid_json')
        '/buildViolation\(\'([a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+)\'\)/',
    ];

    public static function treeProvider(): array
    {
        $cases = [];

        foreach (self::TREES as $tree => $prefix) {
            foreach (self::LOCALES as $locale) {
                $cases[trim($tree . ' ' . $locale)] = [$prefix, $locale];
            }
        }

        return $cases;
    }

    #[DataProvider('treeProvider')]
    public function testEveryConstraintMessageIsDeclaredInTheValidatorsCatalogue(string $prefix, string $locale): void
    {
        $sources = $this->catalogueSources($prefix, sprintf('validators.%s.xlf', $locale));
        $messages = $this->constraintMessages($prefix);
        $this->assertNotEmpty($messages, sprintf('No constraint message found in "%ssrc/", the test itself is broken.', $prefix));

        foreach ($messages as $file => $keys) {
            foreach ($keys as $key) {
                $this->assertContains($key, $sources, sprintf('"%s" uses the constraint message "%s", missing from "%stranslations/validators.%s.xlf".', $file, $key, $prefix, $locale));
            }
        }
    }

    // Collects the message keys a constraint hands the validator, per file
    private function constraintMessages(string $prefix): array
    {
        $messages = [];

        foreach ($this->phpFiles($prefix) as $file) {
            $keys = [];
            foreach (self::PATTERNS as $pattern) {
                preg_match_all($pattern, (string) file_get_contents($file), $matches);
                $keys = array_merge($keys, $matches[1]);
            }

            if ($keys) {
                $messages[substr($file, strlen($this->root()) + 1)] = array_unique($keys);
            }
        }

        return $messages;
    }

    private function phpFiles(string $prefix): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/' . $prefix . 'src'));

        foreach ($iterator as $file) {
            if ('php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function catalogueSources(string $prefix, string $name): array
    {
        $path = $this->root() . '/' . $prefix . 'translations/' . $name;
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
