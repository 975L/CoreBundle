<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\BundleWhatsNewProviderInterface;

class WhatsNewProvider implements BundleWhatsNewProviderInterface
{
    public function getEntries(): array
    {
        $file = \dirname(__DIR__, 2) . '/config/whatsnew.json';
        $entries = [];

        foreach (json_decode(file_get_contents($file), true) ?? [] as $entry) {
            $descriptions = [];
            foreach ($entry['description'] as $description) {
                $descriptions[] = self::resolveDescription($description);
            }

            $entries[] = [
                'date' => new \DateTimeImmutable($entry['date']),
                'description' => $descriptions,
            ];
        }

        return $entries;
    }

    // Picks the description matching the current locale, falling back to English then to the first available translation
    private static function resolveDescription(array $description): string
    {
        return $description[\Locale::getDefault()] ?? $description['en'] ?? reset($description);
    }
}
