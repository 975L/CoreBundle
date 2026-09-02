<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\SameAsProviderInterface;
use c975L\UiBundle\Registry\SameAsRegistry;
use c975L\UiBundle\Service\ContactSnippetBuilder;
use PHPUnit\Framework\TestCase;

class ContactSnippetBuilderTest extends TestCase
{
    private function builder(): ContactSnippetBuilder
    {
        return new ContactSnippetBuilder(new SameAsRegistry());
    }

    // The whole point of the kind: a field left empty is dropped from the graph instead of published blank
    public function testEmptyFieldsAreDroppedFromTheGraph(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Garage Central',
            'telephone' => '',
            'email' => '   ',
            'priceRange' => '',
            'addressStreetAddress' => '',
            'hours' => [],
        ]);

        $this->assertSame(['@context', '@type', 'name'], array_keys($snippet));
    }

    // The registry is read at render time, so what a provider reads from the database reaches the graph
    public function testContributedProfilesArePublishedAsSameAs(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->provider(['https://www.google.com/maps?cid=1', 'https://facebook.com/example']));

        $snippet = new ContactSnippetBuilder($registry)->build(['name' => 'Garage Central']);

        $this->assertSame(['https://www.google.com/maps?cid=1', 'https://facebook.com/example'], $snippet['sameAs']);
    }

    // Two bundles naming the same profile would otherwise publish it twice, which reads as two entities
    public function testTheSameProfileContributedTwiceIsPublishedOnce(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->provider(['https://www.google.com/maps?cid=1', '  ']));
        $registry->addProvider($this->provider(['https://www.google.com/maps?cid=1']));

        $snippet = new ContactSnippetBuilder($registry)->build(['name' => 'Garage Central']);

        $this->assertSame(['https://www.google.com/maps?cid=1'], $snippet['sameAs']);
    }

    // A site whose bundles contribute nothing must not publish an empty property, same rule as every other field
    public function testNoContributedProfileLeavesNoSameAsAtAll(): void
    {
        $this->assertArrayNotHasKey('sameAs', $this->builder()->build(['name' => 'Garage Central']));
    }

    /**
     * @param string[] $urls
     */
    private function provider(array $urls): SameAsProviderInterface
    {
        return new readonly class ($urls) implements SameAsProviderInterface {
            public function __construct(private array $urls)
            {
            }

            public function getSameAs(): array
            {
                return $this->urls;
            }
        };
    }

    public function testNoNameProducesNoGraphAtAll(): void
    {
        $this->assertSame([], $this->builder()->build(['telephone' => '+33 4 50 00 00 00']));
        $this->assertSame('', $this->builder()->buildJson(['telephone' => '+33 4 50 00 00 00']));
    }

    public function testUnknownSchemaTypeFallsBackToTheFirstOfferedOne(): void
    {
        $this->assertSame('LocalBusiness', $this->builder()->build(['name' => 'Garage Central', 'schemaType' => 'Wharever'])['@type']);
        $this->assertSame('AutoRepair', $this->builder()->build(['name' => 'Garage Central', 'schemaType' => 'AutoRepair'])['@type']);
    }

    public function testDescriptionIsPublishedAsPlainText(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Garage Central',
            'description' => "<p>Entretien   &amp; <strong>réparation</strong></p>\n<p>toutes marques</p>",
        ]);

        $this->assertSame('Entretien & réparation toutes marques', $snippet['description']);
    }

    // The complement has no property of its own, schema.org expecting the whole street line in "streetAddress"
    public function testAddressJoinsTheComplementIntoTheStreetLineAndUppercasesTheCountryCode(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Garage Central',
            'addressStreetAddress' => '1083 Rue de Bidaille',
            'addressComplement' => 'Zone artisanale',
            'addressPostalCode' => '74930',
            'addressLocality' => 'Scientrier',
            'addressRegion' => 'Haute-Savoie',
            'addressCountryCode' => 'fr',
        ]);

        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '1083 Rue de Bidaille, Zone artisanale',
            'postalCode' => '74930',
            'addressLocality' => 'Scientrier',
            'addressRegion' => 'Haute-Savoie',
            'addressCountry' => 'FR',
        ], $snippet['address']);
    }

    // An address left entirely empty must not reach the graph as a node carrying nothing but its "@type"
    public function testEmptyAddressIsNotPublished(): void
    {
        $snippet = $this->builder()->build(['name' => 'Garage Central', 'addressStreetAddress' => '', 'addressPostalCode' => '']);

        $this->assertArrayNotHasKey('address', $snippet);
    }

    public function testBothNumbersArePublishedAsATelephoneArray(): void
    {
        $both = $this->builder()->build(['name' => 'Garage Central', 'telephone' => '+33 4 50 00 00 00', 'mobile' => '+33 6 12 34 56 78']);
        $one = $this->builder()->build(['name' => 'Garage Central', 'mobile' => '+33 6 12 34 56 78']);

        $this->assertSame(['+33 4 50 00 00 00', '+33 6 12 34 56 78'], $both['telephone']);
        $this->assertSame('+33 6 12 34 56 78', $one['telephone']);
    }

    public function testGeoNeedsBothCoordinates(): void
    {
        $complete = $this->builder()->build(['name' => 'Garage Central', 'latitude' => '46.1234', 'longitude' => '6.3456']);
        $partial = $this->builder()->build(['name' => 'Garage Central', 'latitude' => '46.1234']);

        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 46.1234, 'longitude' => 6.3456], $complete['geo']);
        $this->assertArrayNotHasKey('geo', $partial);
    }

    // One row per time range, so a business closing for lunch publishes two specifications over the same days
    public function testEachHourRowBecomesItsOwnSpecification(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Garage Central',
            'hours' => [
                ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '9:00', 'closes' => '12:00'],
                ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '14:00', 'closes' => '18:00'],
            ],
        ]);

        $this->assertCount(2, $snippet['openingHoursSpecification']);
        $this->assertSame([
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            // Zero-padded to what schema.org expects
            'opens' => '09:00',
            'closes' => '12:00',
        ], $snippet['openingHoursSpecification'][0]);
    }

    public function testIncompleteOrUnknownHourRowsAreSkipped(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Garage Central',
            'hours' => [
                ['days' => ['Monday'], 'opens' => '09:00', 'closes' => ''],
                ['days' => [], 'opens' => '09:00', 'closes' => '18:00'],
                ['days' => ['Caturday'], 'opens' => '09:00', 'closes' => '18:00'],
                ['days' => ['Monday'], 'opens' => '25:00', 'closes' => '18:00'],
                // Not what the form stores: a misread "6pm" would publish a closing hour before the opening one
                ['days' => ['Monday'], 'opens' => '9am', 'closes' => '6pm'],
                ['days' => ['Monday'], 'opens' => '9h', 'closes' => '18h'],
            ],
        ]);

        $this->assertArrayNotHasKey('openingHoursSpecification', $snippet);
    }

    // A "</script>" typed into any field must not be able to close the tag the graph is printed in
    public function testJsonEscapesTagOpeningCharacters(): void
    {
        $json = $this->builder()->buildJson(['name' => 'Garage Central </script><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
        $this->assertSame('Garage Central </script><script>alert(1)</script>', json_decode($json, true)['name']);
    }

    public function testImageUrlIsPublishedFromTheCallerRatherThanTheData(): void
    {
        $snippet = $this->builder()->build(['name' => 'Garage Central'], 'https://example.com/medias/logo.webp');

        $this->assertSame('https://example.com/medias/logo.webp', $snippet['image']);
        $this->assertArrayNotHasKey('image', $this->builder()->build(['name' => 'Garage Central']));
    }

    public function testContextAndTypeOpenTheGraph(): void
    {
        $json = $this->builder()->buildJson(['name' => 'Garage Central', 'schemaType' => 'AutoRepair']);

        $this->assertStringStartsWith('{"@context":"https://schema.org","@type":"AutoRepair"', $json);
    }
}
