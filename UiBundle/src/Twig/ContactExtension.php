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

    // Splits the days of one opening range into runs of consecutive days, so a template can print "Monday - Friday" rather than the five of them; a lone day comes back as a one-entry run, and the week order is the stored one
    #[AsTwigFunction('contact_day_runs')]
    public function dayRuns(array $days): array
    {
        $ordered = array_values(array_intersect(ContactSnippetBuilder::DAYS, $days));
        $runs = [];

        foreach ($ordered as $day) {
            $previous = end($runs) ?: null;
            $isNext = null !== $previous
                && array_search($day, ContactSnippetBuilder::DAYS, true) === array_search(end($previous), ContactSnippetBuilder::DAYS, true) + 1;

            if ($isNext) {
                $runs[\count($runs) - 1][] = $day;

                continue;
            }

            $runs[] = [$day];
        }

        return $runs;
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
