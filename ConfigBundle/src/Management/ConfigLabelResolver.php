<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use Symfony\Contracts\Translation\TranslatorInterface;

// The one place deciding what text a config's label shows. The c975L bundles ship a 'site_config' translation for each of their own slugs, but an app declaring its own configs in configs.json usually writes the label in clear - being private and monolingual, it has no reason to maintain an xlf. Without this, such a label reached the screen as the raw "label.xxx" key, while the description right next to it displayed fine, the template translating the stored text and falling back to it unchanged
class ConfigLabelResolver
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The translation of the slug-derived key, or the label stored by the import when that key has none - the raw key only remains when there is no stored label either, which at least stays searchable
    public function resolve(Config $config): string
    {
        $key = $config->getLabelTranslationKey();
        $translated = $this->translator->trans($key, [], 'site_config');

        if ($translated !== $key) {
            return $translated;
        }

        return '' !== $config->getLabel() ? $config->getLabel() : $key;
    }
}
