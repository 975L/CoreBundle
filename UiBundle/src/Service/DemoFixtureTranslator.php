<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Translation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The demo dataset said in every language the site declares, read off the very catalogues it was seeded from: a sample catalog holds translation keys rather than prose (see ShopSampleCatalog), and the same key read in another language is that row's translation, already shipped with the bundle. Two passes, a translation naming its owner by identifier: a provider stages its rows as it builds them and yields them through DemoFixtureLinkerInterface once the loader's first flush is in
class DemoFixtureTranslator
{
    /** @var list<array{owner: object, ownerType: string, domain: string, keys: array<string, string>, format: ?callable}> */
    private array $staged = [];

    /** @param list<string> $enabledLocales */
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    /**
     * An entity being seeded, and which catalogue key each of its translatable fields was written from.
     *
     * @param array<string, string>           $keys   field => the key the value was read from, fields carrying prose of their own left out
     * @param (callable(string): string)|null $format what the provider wrapped the words in before storing them - a block holding its prose as "<div>...</div>" stores its translation the same way, or the two read as two different texts to whatever compares them
     */
    public function stage(object $owner, string $ownerType, string $domain, array $keys, ?callable $format = null): void
    {
        if ([] !== $keys && [] !== $this->translatable()) {
            $this->staged[] = ['owner' => $owner, 'ownerType' => $ownerType, 'domain' => $domain, 'keys' => $keys, 'format' => $format];
        }
    }

    // What was staged, now that the rows have identifiers, and nothing else: the staging is emptied as it is read, a loader running twice in one process seeding the second dataset alone
    /** @return iterable<Translation> */
    public function translations(): iterable
    {
        $staged = $this->staged;
        $this->staged = [];

        foreach ($staged as $entry) {
            $id = $this->identifier($entry['owner']);

            // Not flushed: a fixture its provider gave up on - a picture missing from the disk - takes its translations with it
            if (null === $id) {
                continue;
            }

            foreach ($entry['keys'] as $field => $key) {
                $source = $this->translator->trans($key, [], $entry['domain'], $this->defaultLocale);

                foreach ($this->translatable() as $locale) {
                    $value = $this->translator->trans($key, [], $entry['domain'], $locale);

                    // A catalogue saying nothing in that language gives the key back, or the very words the site is written in: an untranslated row is what a reader already sees, and a row saying that is a row saying nothing
                    if ($value === $key || $value === $source) {
                        continue;
                    }

                    yield new Translation($entry['ownerType'], $id, $field, $locale)->setValue(null === $entry['format'] ? $value : ($entry['format'])($value));
                }
            }
        }
    }

    // The languages a translation may be written in - every one the site declares but the one it is written in. Read off "kernel.enabled_locales" rather than off ConfigBundle's SiteLocales, which lives a layer above this one
    /** @return list<string> */
    private function translatable(): array
    {
        return array_values(array_filter($this->enabledLocales, fn (string $locale): bool => $locale !== $this->defaultLocale));
    }

    // Doctrine's own identifier, whatever the entity calls itself: every c975L entity carries getId(), and one that does not is left out rather than failing the load
    private function identifier(object $owner): ?int
    {
        if (!method_exists($owner, 'getId')) {
            return null;
        }

        $id = $owner->getId();

        return \is_int($id) ? $id : null;
    }
}
