<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\ContactSnippetBuilder;
use c975L\UiBundle\Service\GoogleMapsLinkBuilder;
use Twig\Attribute\AsTwigFunction;

class ContactExtension
{
    public function __construct(
        private readonly ContactSnippetBuilder $snippetBuilder,
        private readonly GoogleMapsLinkBuilder $googleMapsLinkBuilder,
    ) {
    }

    // Lays the opening ranges out day by day, the whole week in order: each day gets every range naming it, earliest first, and a day no range names comes back empty - closed. A visitor looks up one day, and finds it on its own line rather than spread over the rows it was entered as
    #[AsTwigFunction('contact_week')]
    public function week(array $hours): array
    {
        $week = array_fill_keys(ContactSnippetBuilder::DAYS, []);

        foreach ($hours as $row) {
            $opens = $row['opens'] ?? '';
            $closes = $row['closes'] ?? '';
            if ('' === $opens || '' === $closes) {
                continue;
            }

            foreach (array_intersect($row['days'] ?? [], ContactSnippetBuilder::DAYS) as $day) {
                $week[$day][] = ['opens' => $opens, 'closes' => $closes];
            }
        }

        // Padded for the comparison only, so a hand-entered "9:00" still sorts before "14:00"
        foreach ($week as &$ranges) {
            usort($ranges, static fn (array $a, array $b) => strcmp(str_pad($a['opens'], 5, '0', STR_PAD_LEFT), str_pad($b['opens'], 5, '0', STR_PAD_LEFT)));
        }
        unset($ranges);

        return $week;
    }

    // The place's address on Google Maps, built from the block's own coordinates or postal address - empty when it holds neither. A plain link anyone opens, which costs nothing and loads no script: the Maps JavaScript API the "map" block draws with is the other, billed half of Google Maps
    #[AsTwigFunction('google_maps_url')]
    public function googleMapsUrl(array $data): string
    {
        return $this->googleMapsLinkBuilder->build($data) ?? '';
    }

    // Returns the <script type="application/ld+json"> payload for a "contact_details" block, empty when there is nothing to publish
    #[AsTwigFunction('contact_json_ld', isSafe: ['html'])]
    public function jsonLd(array $data, ?string $imageUrl = null): string
    {
        return $this->snippetBuilder->buildJson($data, $imageUrl);
    }
}
