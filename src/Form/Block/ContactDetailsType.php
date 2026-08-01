<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use c975L\UiBundle\Service\ContactSnippetBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Url;

// Every field is optional: the template renders only what was filled in, and ContactSnippetBuilder drops the rest
// from the published graph rather than emitting it empty - which is what a microdata-based kind could not do.
class ContactDetailsType extends AbstractType
{
    use HasAnchorFieldTrait;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The name, not a title: it is what the anchor falls back to when none is typed
        $this->addAnchorField($builder, $this->anchorSlugger, 'name');

        $builder
            ->add('schemaType', ChoiceType::class, [
                'label' => 'label.schema_type',
                'help' => 'label.schema_type_help',
                'choices' => array_combine(ContactSnippetBuilder::TYPES, ContactSnippetBuilder::TYPES),
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'help' => 'label.contact_details_name_help',
                'required' => false,
            ])
            ->add('description', TrixEditorType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
            ->add('addressStreetAddress', TextType::class, [
                'label' => 'label.street_address',
                'required' => false,
            ])
            ->add('addressComplement', TextType::class, [
                'label' => 'label.address_complement',
                'required' => false,
            ])
            ->add('addressPostalCode', TextType::class, [
                'label' => 'label.postal_code',
                'required' => false,
            ])
            ->add('addressLocality', TextType::class, [
                'label' => 'label.city',
                'required' => false,
            ])
            ->add('addressRegion', TextType::class, [
                'label' => 'label.region',
                'required' => false,
            ])
            ->add('addressCountryName', TextType::class, [
                'label' => 'label.country',
                'required' => false,
            ])
            ->add('addressCountryCode', TextType::class, [
                'label' => 'label.country_code',
                'required' => false,
            ])
            ->add('telephone', TextType::class, [
                'label' => 'label.phone',
                'required' => false,
            ])
            ->add('mobile', TextType::class, [
                'label' => 'label.mobile',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'constraints' => [new Email()],
                'required' => false,
            ])
            ->add('url', UrlType::class, array_merge($this->urlOptions(), ['label' => 'label.website']))
            ->add('hours', CollectionType::class, [
                'label' => 'label.opening_hours',
                'entry_type' => ContactHoursType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ])
            ->add('priceRange', TextType::class, [
                'label' => 'label.price_range',
                'required' => false,
            ])
            ->add('mapUrl', UrlType::class, array_merge($this->urlOptions(), ['label' => 'label.map_url', 'help' => 'label.map_url_help']))
            ->add('latitude', TextType::class, [
                'label' => 'label.latitude',
                'required' => false,
            ])
            ->add('longitude', TextType::class, [
                'label' => 'label.longitude',
                'required' => false,
            ]);
    }

    // "default_protocol" prepends on submit, so a bare "example.com" is stored absolute: a relative href resolves
    // against SiteBundle's sitewide <base href> - it would point back at the site - and schema.org takes no such URL
    private function urlOptions(): array
    {
        return [
            'default_protocol' => 'https',
            'constraints' => [new Url()],
            'required' => false,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
