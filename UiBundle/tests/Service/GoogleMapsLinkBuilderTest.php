<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\GoogleMapsLinkBuilder;
use PHPUnit\Framework\TestCase;

class GoogleMapsLinkBuilderTest extends TestCase
{
    // Coordinates name one point, where an address is a search whose first answer Google picks for the visitor
    public function testCoordinatesAreUsedBeforeTheAddress(): void
    {
        $url = new GoogleMapsLinkBuilder()->build([
            'latitude' => '45.8992',
            'longitude' => '6.1294',
            'addressStreetAddress' => '1 rue de l\'Exemple',
            'addressLocality' => 'Annecy',
        ]);

        $this->assertSame('https://www.google.com/maps/search/?api=1&query=45.8992%2C6.1294', $url);
    }

    public function testTheAddressIsUsedWhenThereAreNoCoordinates(): void
    {
        $url = new GoogleMapsLinkBuilder()->build([
            'addressStreetAddress' => '1 rue de l\'Exemple',
            'addressPostalCode' => '74000',
            'addressLocality' => 'Annecy',
            'addressCountryName' => 'France',
        ]);

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('1 rue de l\'Exemple, 74000, Annecy, France'),
            $url
        );
    }

    // A building or a floor names nothing on a map, and only makes the search miss the street it belongs to
    public function testTheAddressComplementIsLeftOut(): void
    {
        $url = (string) new GoogleMapsLinkBuilder()->build([
            'addressStreetAddress' => '1 rue de l\'Exemple',
            'addressComplement' => 'Bâtiment B',
            'addressLocality' => 'Annecy',
        ]);

        $this->assertStringNotContainsString(rawurlencode('Bâtiment B'), $url);
    }

    // Half a pair places nothing, and would be read as a search for the number itself
    public function testHalfAPairOfCoordinatesFallsBackOnTheAddress(): void
    {
        $url = (string) new GoogleMapsLinkBuilder()->build([
            'latitude' => '45.8992',
            'longitude' => '',
            'addressLocality' => 'Annecy',
        ]);

        $this->assertStringEndsWith('query=Annecy', $url);
    }

    // A country alone, or a postal code alone, points at no place worth a button
    public function testAnAddressNamingNoStreetAndNoTownBuildsNothing(): void
    {
        $this->assertNull(new GoogleMapsLinkBuilder()->build([
            'addressPostalCode' => '74000',
            'addressCountryName' => 'France',
        ]));
    }

    public function testABlockHoldingNeitherBuildsNothing(): void
    {
        $this->assertNull(new GoogleMapsLinkBuilder()->build([]));
    }
}
