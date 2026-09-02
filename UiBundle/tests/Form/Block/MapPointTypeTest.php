<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\MapPointType;
use c975L\UiBundle\Service\MapGeocoderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\Validation;

// A real form, submitted: the geocoding runs in a SUBMIT listener and the invariants in a Callback constraint, so nothing short of a full submit tells whether the two work together
class MapPointTypeTest extends TestCase
{
    private const array COORDINATES = ['latitude' => '45.8992', 'longitude' => '6.1294'];

    public function testAnAddressIsResolvedIntoCoordinates(): void
    {
        $form = $this->submit(['label' => 'Annecy', 'mode' => 'address', 'address' => 'Annecy'], $this->geocoder(self::COORDINATES));

        $this->assertTrue($form->isValid());
        $this->assertSame('45.8992', $form->getData()['latitude']);
        $this->assertSame('6.1294', $form->getData()['longitude']);
        // Kept beside the coordinates so the next save knows the address has not moved
        $this->assertSame('Annecy', $form->getData()['geocodedAddress']);
    }

    // A courtesy to Nominatim, whose usage policy asks for exactly this, and one less call an editor waits on every time they fix a typo elsewhere in the block
    public function testAnAddressAlreadyResolvedIsNotLookedUpAgain(): void
    {
        $geocoder = $this->createMock(MapGeocoderInterface::class);
        $geocoder->expects($this->never())->method('geocode');

        $form = $this->submit(
            ['label' => 'Annecy', 'mode' => 'address', 'address' => 'Annecy'] + self::COORDINATES,
            $geocoder,
            ['geocodedAddress' => 'Annecy'] + self::COORDINATES
        );

        $this->assertTrue($form->isValid());
    }

    // An address the editor has since rewritten: keeping the coordinates of the previous one would pin the marker on a place they no longer named
    public function testARewrittenAddressIsLookedUpAgain(): void
    {
        $form = $this->submit(
            ['label' => 'Chamonix', 'mode' => 'address', 'address' => 'Chamonix'],
            $this->geocoder(['latitude' => '45.9237', 'longitude' => '6.8694']),
            ['geocodedAddress' => 'Annecy'] + self::COORDINATES
        );

        $this->assertSame('45.9237', $form->getData()['latitude']);
    }

    // Reported on the field the editor can act on, rather than saved as a point the map has nowhere to draw
    public function testAnAddressThatResolvesToNothingIsRefused(): void
    {
        $form = $this->submit(['label' => 'Nowhere', 'mode' => 'address', 'address' => 'Nowhere at all'], $this->geocoder(null));

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('address')->getErrors());
        $this->assertSame('text.map_point_address_not_found', $form->get('address')->getErrors()[0]->getMessage());
    }

    public function testAnEmptyAddressIsRefusedBeforeAnythingIsLookedUp(): void
    {
        $geocoder = $this->createMock(MapGeocoderInterface::class);
        $geocoder->expects($this->never())->method('geocode');

        $form = $this->submit(['label' => 'Annecy', 'mode' => 'address', 'address' => ''], $geocoder);

        $this->assertFalse($form->isValid());
        $this->assertSame('text.map_point_address_required', $form->get('address')->getErrors()[0]->getMessage());
    }

    // The only way to pin what no postal address names - and nothing is geocoded for it, the editor having said where the point is
    public function testCoordinatesArePlacedWithoutAskingTheGeocoderAnything(): void
    {
        $geocoder = $this->createMock(MapGeocoderInterface::class);
        $geocoder->expects($this->never())->method('geocode');

        $form = $this->submit(['label' => 'Un pré', 'mode' => 'coordinates'] + self::COORDINATES, $geocoder);

        $this->assertTrue($form->isValid());
        $this->assertSame('45.8992', $form->getData()['latitude']);
    }

    // One of the two alone places nothing: a marker takes both
    public function testHalfAPairOfCoordinatesIsRefused(): void
    {
        $form = $this->submit(['label' => 'Un pré', 'mode' => 'coordinates', 'latitude' => '45.8992', 'longitude' => ''], $this->geocoder(null));

        $this->assertFalse($form->isValid());
        $this->assertSame('text.map_point_coordinates_required', $form->get('latitude')->getErrors()[0]->getMessage());
    }

    private function geocoder(?array $coordinates): MapGeocoderInterface
    {
        $geocoder = $this->createStub(MapGeocoderInterface::class);
        $geocoder->method('geocode')->willReturn($coordinates);

        return $geocoder;
    }

    private function submit(array $submitted, MapGeocoderInterface $geocoder, array $stored = []): FormInterface
    {
        // Preloaded rather than built by the factory: the type takes its geocoder in its constructor, which is what this test replaces
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([new MapPointType($geocoder)], []))
            ->getFormFactory();

        $form = $factory->create(MapPointType::class, $stored);
        $form->submit($submitted + ['address' => '', 'latitude' => '', 'longitude' => '', 'text' => '', 'url' => '']);

        return $form;
    }
}
