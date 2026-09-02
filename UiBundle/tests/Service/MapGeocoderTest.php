<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\MapGeocoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MapGeocoderTest extends TestCase
{
    public function testItReadsTheCoordinatesOfTheFirstMatch(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            ['lat' => '45.8992', 'lon' => '6.1294'],
            ['lat' => '0', 'lon' => '0'],
        ], \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]));

        $this->assertSame(
            ['latitude' => '45.8992', 'longitude' => '6.1294'],
            new MapGeocoder($client, new NullLogger())->geocode('Annecy')
        );
    }

    // Nominatim's usage policy refuses a caller that does not identify itself, and answers a bare client with a 403 - which would make every address in the back-office look unknown
    public function testItIdentifiesItselfAndAsksForOneResult(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertStringStartsWith('https://nominatim.openstreetmap.org/search', $url);
            $this->assertStringContainsString('limit=1', $url);
            $this->assertContains('User-Agent: c975L-UiBundle/1.0 (https://github.com/975L/CoreBundle)', $options['headers']);

            return new MockResponse('[]', ['response_headers' => ['content-type' => 'application/json']]);
        });

        new MapGeocoder($client, new NullLogger())->geocode('Annecy');
    }

    // An address nobody knows is not an error to report to the visitor: the caller reads the null and says so on the field the editor can still correct
    public function testAnAddressThatMatchesNothingResolvesToNull(): void
    {
        $client = new MockHttpClient(new MockResponse('[]', ['response_headers' => ['content-type' => 'application/json']]));

        $this->assertNull(new MapGeocoder($client, new NullLogger())->geocode('Nowhere at all'));
    }

    // The service being down must not take the whole block form down with it - a back-office save is what waits on this call
    public function testAServiceThatIsDownResolvesToNullRatherThanThrowing(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new class extends \RuntimeException implements TransportExceptionInterface {
            };
        });

        $this->assertNull(new MapGeocoder($client, new NullLogger())->geocode('Annecy'));
    }

    // A blank address is nothing to look up, and asking Nominatim for it would spend a call on an empty field
    public function testABlankAddressIsNotEvenLookedUp(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => throw new \LogicException('The geocoder must not be called at all.'));

        $this->assertNull(new MapGeocoder($client, new NullLogger())->geocode('   '));
    }
}
