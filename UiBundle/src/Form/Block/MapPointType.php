<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Service\MapGeocoderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

// One place of MapType's "points" collection. An editor says where it is one of two ways, and says so explicitly rather than leaving the form to guess from which field they filled: an address, resolved to coordinates once here (see the SUBMIT listener below), or the coordinates themselves, which is the only way to pin something a postal address does not name - a crash site, a clearing, a memorial in a field
class MapPointType extends AbstractType
{
    public const string MODE_ADDRESS = 'address';
    public const string MODE_COORDINATES = 'coordinates';

    public function __construct(private readonly MapGeocoderInterface $geocoder)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'label.map_point_label',
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'label.map_point_mode',
                'choices' => [
                    'label.map_point_mode_address' => self::MODE_ADDRESS,
                    'label.map_point_mode_coordinates' => self::MODE_COORDINATES,
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'label.map_point_address',
                'help' => 'label.map_point_address_help',
                'required' => false,
            ])
            ->add('latitude', TextType::class, [
                'label' => 'label.map_point_latitude',
                'required' => false,
            ])
            ->add('longitude', TextType::class, [
                'label' => 'label.map_point_longitude',
                'required' => false,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'label.map_point_text',
                'help' => 'label.map_point_text_help',
                'required' => false,
            ])
            ->add('url', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ]);

        // SUBMIT and not POST_SUBMIT, same as HasAnchorFieldTrait: this is where a whole array of block data can still be written back, and the constraint below then validates what the geocoding actually produced rather than what was typed
        $builder->addEventListener(FormEvents::SUBMIT, $this->resolveCoordinates(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
            'constraints' => [new Callback($this->validate(...))],
        ]);
    }

    // Fills the coordinates from the address, when that is how the editor chose to place the point and the address is one this form has not already resolved
    private function resolveCoordinates(FormEvent $event): void
    {
        $data = $event->getData();

        if ($this->needsGeocoding($data)) {
            $address = trim((string) ($data['address'] ?? ''));
            $coordinates = $this->geocoder->geocode($address);

            // Left empty on a failure rather than keeping the coordinates of the previous address, which would pin the marker on a place the editor no longer named - the constraint below is what tells them about it
            $data['latitude'] = $coordinates['latitude'] ?? '';
            $data['longitude'] = $coordinates['longitude'] ?? '';
            $data['geocodedAddress'] = null === $coordinates ? null : $address;
        }

        $event->setData($data);
    }

    // "geocodedAddress" is kept beside the coordinates for exactly this: an address left untouched since it was resolved is not sent to Nominatim again - a courtesy its usage policy asks for, and one less call an editor waits on
    private function needsGeocoding(array $data): bool
    {
        $address = trim((string) ($data['address'] ?? ''));

        if (self::MODE_ADDRESS !== ($data['mode'] ?? self::MODE_ADDRESS) || '' === $address) {
            return false;
        }

        return $address !== ($data['geocodedAddress'] ?? null) || !$this->hasCoordinates($data);
    }

    // A point with nowhere to be drawn is not saved: it would leave the map short of a marker the editor believes they placed, with nothing on any screen saying otherwise
    // Bracketed paths ("[address]", not "address"): block data is an array, so that is the property path its children are mapped under - a dotted one is read as an object property, matches no child, and leaves the message on the row itself where the field it is about shows nothing
    private function validate(mixed $data, ExecutionContextInterface $context): void
    {
        if (!is_array($data)) {
            return;
        }

        $isAddress = self::MODE_ADDRESS === ($data['mode'] ?? self::MODE_ADDRESS);

        if ($isAddress && '' === trim((string) ($data['address'] ?? ''))) {
            $context->buildViolation('text.map_point_address_required')->atPath('[address]')->addViolation();

            return;
        }

        if ($isAddress && !$this->hasCoordinates($data)) {
            $context->buildViolation('text.map_point_address_not_found')->atPath('[address]')->addViolation();

            return;
        }

        if (!$isAddress && !$this->hasCoordinates($data)) {
            $context->buildViolation('text.map_point_coordinates_required')->atPath('[latitude]')->addViolation();
        }
    }

    private function hasCoordinates(array $data): bool
    {
        return is_numeric($data['latitude'] ?? null) && is_numeric($data['longitude'] ?? null);
    }
}
