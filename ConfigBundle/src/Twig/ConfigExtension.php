<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\ConfigTranslator;
use Twig\Attribute\AsTwigFunction;

class ConfigExtension
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ConfigTranslator $configTranslator,
    ) {
    }

    // The language is the one a page is read in unless it is named: a book sheet renders in the book's own (see BookBundle), which is not the visitor's. A site declaring a single language never leaves the value it was typed in (see ConfigTranslator)
    #[AsTwigFunction('config')]
    public function getConfig(string $slug, ?string $locale = null): mixed
    {
        return $this->configTranslator->value($slug, $this->configService->get($slug), $locale);
    }
}
