<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Locales;

// The languages a site offers, and the one place anything asks for them - read from "framework.enabled_locales" rather than configs.json, Symfony itself restricting a route's "_locale" to that list and compiling only the catalogues it names
// A site declaring nothing gets its default locale alone, and everything below then behaves exactly as it did
class SiteLocales
{
    // Read once per request: LocaleListener asks on every front request, and the answer cannot change mid-request
    private ?array $all = null;

    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    /**
     * Every language the site offers, the one it is written in first.
     *
     * @return list<string>
     */
    public function all(): array
    {
        if (null !== $this->all) {
            return $this->all;
        }

        $declared = $this->declared();

        // The default locale is what every untranslated value already is, so it belongs to the list whether or not the config names it
        $locales = array_values(array_unique([$this->defaultLocale, ...$declared]));

        return $this->all = $locales;
    }

    /**
     * The languages a translation may be written in: every language offered but the default, which is the text itself.
     *
     * @return list<string>
     */
    public function translatable(): array
    {
        return array_values(array_filter($this->all(), fn (string $locale) => $locale !== $this->defaultLocale));
    }

    // Nothing to translate, and no language to choose from, on a site offering a single one
    public function isMultilingual(): bool
    {
        return \count($this->all()) > 1;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * What the site declared, anything the Intl catalogue does not know dropped rather than refused: a typo would
     * otherwise 500 every back-office page through EasyAdmin's own Locale::new() - the very screens that would fix it.
     *
     * @return list<string>
     */
    private function declared(): array
    {
        return array_values(array_filter($this->enabledLocales, Locales::exists(...)));
    }
}
