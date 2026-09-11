<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use Twig\Attribute\AsTwigFunction;

// "localized_path" where a template would say "path": the same url, read in the language the page around it is being read in (see LocalizedUrlGenerator)
class LocalizedPathExtension
{
    public function __construct(private readonly LocalizedUrlGenerator $localizedUrlGenerator)
    {
    }

    /**
     * @param array<string, mixed> $parameters
     * @param list<string>|null    $locales    the languages the target really answers in, null when it answers in all of them
     */
    #[AsTwigFunction('localized_path')]
    public function localizedPath(string $route, array $parameters = [], ?array $locales = null): string
    {
        return $this->localizedUrlGenerator->path($route, $parameters, $locales);
    }

    // The screen being read, offered in each language the site declares - what a language menu is made of where there is no Page behind the screen (see LocalizedUrlGenerator::screenLanguages)
    /** @return array<string, string> locale => url */
    #[AsTwigFunction('screen_languages')]
    public function screenLanguages(): array
    {
        return $this->localizedUrlGenerator->screenLanguages();
    }
}
