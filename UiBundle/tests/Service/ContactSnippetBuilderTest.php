<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\ContactSnippetBuilder;
use PHPUnit\Framework\TestCase;

class ContactSnippetBuilderTest extends TestCase
{
    private function builder(): ContactSnippetBuilder
    {
        return new ContactSnippetBuilder();
    }

    // The whole point of the kind: a field left empty is dropped from the graph instead of published blank
    public function testEmptyFieldsAreDroppedFromTheGraph(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Autotech',
            'telephone' => '',
            'email' => '   ',
            'priceRange' => '',
            'addressStreetAddress' => '',
            'hours' => [],
        ]);

        $this->assertSame(['@context', '@type', 'name'], array_keys($snippet));
    }

    public function testNoNameProducesNoGraphAtAll(): void
    {
        $this->assertSame([], $this->builder()->build(['telephone' => '+33 4 50 00 00 00']));
        $this->assertSame('', $this->builder()->buildJson(['telephone' => '+33 4 50 00 00 00']));
    }

    public function testUnknownSchemaTypeFallsBackToTheFirstOfferedOne(): void
    {
        $this->assertSame('LocalBusiness', $this->builder()->build(['name' => 'Autotech', 'schemaType' => 'Wharever'])['@type']);
        $this->assertSame('AutoRepair', $this->builder()->build(['name' => 'Autotech', 'schemaType' => 'AutoRepair'])['@type']);
    }

    public function testDescriptionIsPublishedAsPlainText(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Autotech',
            'description' => "<p>Entretien   &amp; <strong>réparation</strong></p>\n<p>toutes marques</p>",
        ]);

        $this->assertSame('Entretien & réparation toutes marques', $snippet['description']);
    }

    // The complement has no property of its own, schema.org expecting the whole street line in "streetAddress"
    public function testAddressJoinsTheComplementIntoTheStreetLineAndUppercasesTheCountryCode(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Autotech',
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
        $snippet = $this->builder()->build(['name' => 'Autotech', 'addressStreetAddress' => '', 'addressPostalCode' => '']);

        $this->assertArrayNotHasKey('address', $snippet);
    }

    public function testBothNumbersArePublishedAsATelephoneArray(): void
    {
        $both = $this->builder()->build(['name' => 'Autotech', 'telephone' => '+33 4 50 00 00 00', 'mobile' => '+33 6 12 34 56 78']);
        $one = $this->builder()->build(['name' => 'Autotech', 'mobile' => '+33 6 12 34 56 78']);

        $this->assertSame(['+33 4 50 00 00 00', '+33 6 12 34 56 78'], $both['telephone']);
        $this->assertSame('+33 6 12 34 56 78', $one['telephone']);
    }

    public function testGeoNeedsBothCoordinates(): void
    {
        $complete = $this->builder()->build(['name' => 'Autotech', 'latitude' => '46.1234', 'longitude' => '6.3456']);
        $partial = $this->builder()->build(['name' => 'Autotech', 'latitude' => '46.1234']);

        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 46.1234, 'longitude' => 6.3456], $complete['geo']);
        $this->assertArrayNotHasKey('geo', $partial);
    }

    // One row per time range, so a business closing for lunch publishes two specifications over the same days
    public function testEachHourRowBecomesItsOwnSpecification(): void
    {
        $snippet = $this->builder()->build([
            'name' => 'Autotech',
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
            'name' => 'Autotech',
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
        $json = $this->builder()->buildJson(['name' => 'Autotech </script><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
        $this->assertSame('Autotech </script><script>alert(1)</script>', json_decode($json, true)['name']);
    }

    public function testImageUrlIsPublishedFromTheCallerRatherThanTheData(): void
    {
        $snippet = $this->builder()->build(['name' => 'Autotech'], 'https://autotech-automobile.fr/medias/logo.webp');

        $this->assertSame('https://autotech-automobile.fr/medias/logo.webp', $snippet['image']);
        $this->assertArrayNotHasKey('image', $this->builder()->build(['name' => 'Autotech']));
    }

    public function testContextAndTypeOpenTheGraph(): void
    {
        $json = $this->builder()->buildJson(['name' => 'Autotech', 'schemaType' => 'AutoRepair']);

        $this->assertStringStartsWith('{"@context":"https://schema.org","@type":"AutoRepair"', $json);
    }
}
