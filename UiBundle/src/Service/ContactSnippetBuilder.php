<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Builds the schema.org graph a "contact_details" block publishes as JSON-LD, out of the very fields it displays.
// Display and structured data are rendered apart on purpose: microdata would pin every itemprop to a displayed element, so a field left empty - and they are all optional here - would leave an empty node behind. A graph assembled here instead simply drops what wasn't filled in, and can carry what isn't displayed (geo, image URL).
class ContactSnippetBuilder
{
    // Offered by ContactDetailsType; all of them are LocalBusiness/Organization subtypes accepting the properties below
    public const TYPES = [
        'LocalBusiness',
        'AutoRepair',
        'Organization',
        'ProfessionalService',
        'Store',
        'Restaurant',
    ];

    // schema.org's own day names, stored as-is so no mapping is needed here; the display side translates them
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // $imageUrl is resolved by the caller rather than read from the data: only a template can turn an attached Media into an absolute URL
    public function build(array $data, ?string $imageUrl = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        // No name, no graph: a business node without one indexes nothing, and the block stays a plain display block
        if ('' === $name) {
            return [];
        }

        $type = (string) ($data['schemaType'] ?? '');

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => \in_array($type, self::TYPES, true) ? $type : self::TYPES[0],
            'name' => $name,
            'description' => $this->plainText($data['description'] ?? ''),
            'image' => trim((string) $imageUrl),
            'telephone' => $this->telephones($data),
            'email' => $this->value($data, 'email'),
            'url' => $this->value($data, 'url'),
            'priceRange' => $this->value($data, 'priceRange'),
            'hasMap' => $this->value($data, 'mapUrl'),
            'address' => $this->address($data),
            'geo' => $this->geo($data),
            'openingHoursSpecification' => $this->openingHours($data),
        ]);
    }

    // The same graph, encoded for a <script type="application/ld+json">; empty string when there is nothing to publish
    public function buildJson(array $data, ?string $imageUrl = null): string
    {
        $snippet = $this->build($data, $imageUrl);

        if ([] === $snippet) {
            return '';
        }

        // JSON_HEX_TAG matters: it turns a "</script>" typed into any field into <, which no browser closes the tag on
        return json_encode($snippet, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    // Both numbers when both are filled in: schema.org reads a "telephone" array as several numbers for the same node
    private function telephones(array $data): array | string
    {
        $numbers = array_values(array_filter([$this->value($data, 'telephone'), $this->value($data, 'mobile')]));

        return 1 === \count($numbers) ? $numbers[0] : $numbers;
    }

    private function address(array $data): array
    {
        // The complement (building, floor...) has no property of its own, schema.org expecting the whole street line in "streetAddress"
        $street = array_filter([$this->value($data, 'addressStreetAddress'), $this->value($data, 'addressComplement')]);

        $address = $this->clean([
            'streetAddress' => implode(', ', $street),
            'postalCode' => $this->value($data, 'addressPostalCode'),
            'addressLocality' => $this->value($data, 'addressLocality'),
            'addressRegion' => $this->value($data, 'addressRegion'),
            // The code, not the name: schema.org asks for the ISO 3166-1 alpha-2 one
            'addressCountry' => strtoupper($this->value($data, 'addressCountryCode')),
        ]);

        // Prepended last, so an address left entirely empty stays an empty array the caller drops rather than a bare "@type"
        return [] === $address ? [] : ['@type' => 'PostalAddress'] + $address;
    }

    private function geo(array $data): array
    {
        $latitude = $this->value($data, 'latitude');
        $longitude = $this->value($data, 'longitude');

        // One coordinate alone locates nothing
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return [];
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }

    // One OpeningHoursSpecification per filled-in row; a day the business is closed is simply a day no row names
    private function openingHours(array $data): array
    {
        $specifications = [];

        foreach ($data['hours'] ?? [] as $row) {
            $days = array_values(array_intersect(self::DAYS, (array) ($row['days'] ?? [])));
            $opens = $this->time($row['opens'] ?? null);
            $closes = $this->time($row['closes'] ?? null);

            if ([] === $days || '' === $opens || '' === $closes) {
                continue;
            }

            $specifications[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days,
                'opens' => $opens,
                'closes' => $closes,
            ];
        }

        return $specifications;
    }

    // The form hands over "HH:MM" already (a native time picker, see ContactHoursType); anything else - hand-edited data, an import - is dropped rather than guessed at, a misread "6pm" publishing a closing hour before the opening one
    private function time(mixed $value): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $value), $matches)) {
            return '';
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        return $hours > 23 || $minutes > 59 ? '' : sprintf('%02d:%02d', $hours, $minutes);
    }

    // The description is rich text; a graph carries the words only
    private function plainText(mixed $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function value(array $data, string $key): string
    {
        return trim((string) ($data[$key] ?? ''));
    }

    // Drops everything left empty, so an unfilled field never reaches the graph as a blank property
    private function clean(array $snippet): array
    {
        return array_filter($snippet, static fn ($value) => !\in_array($value, ['', [], null], true));
    }
}
