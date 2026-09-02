<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

// Which places a map shows, and how tall it stands. Which provider draws it is deliberately not a field here: an API key and a billing account are a site-wide decision, taken once in the settings ("ui-map-provider", "ui-map-google-api-key"), where a per-block choice would ask an editor composing a page to take it again on every map they place
class MapType extends AbstractType
{
    use HasAnchorFieldTrait;

    // The zoom levels every tile provider agrees on - 0 is the whole planet in one tile, 19 is a house. Only read when the map holds a single point: several are framed by their own bounds instead (see assets/js/map.js)
    private const int ZOOM_MIN = 1;
    private const int ZOOM_MAX = 19;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            // A class and not a height in pixels: an inline style is what a Content-Security-Policy refuses, and three sizes are what a page composed of blocks actually needs
            ->add('height', ChoiceType::class, [
                'label' => 'label.map_height',
                'choices' => [
                    'label.map_height_small' => 'small',
                    'label.map_height_medium' => 'medium',
                    'label.map_height_large' => 'large',
                ],
            ])
            ->add('zoom', IntegerType::class, [
                'label' => 'label.map_zoom',
                'help' => 'label.map_zoom_help',
                'constraints' => [new Range(min: self::ZOOM_MIN, max: self::ZOOM_MAX)],
            ])
            ->add('points', CollectionType::class, [
                'label' => 'label.map_points',
                'entry_type' => MapPointType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
