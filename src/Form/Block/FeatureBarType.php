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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class FeatureBarType extends AbstractType
{
    use HasAnchorFieldTrait;
    use HasBackgroundFieldTrait;

    // The row is sized for this many columns; a sixth entry lands alone on a second row, flush left
    public const MAX_ITEMS = 5;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // No "title" field on this kind - the anchor field must be typed explicitly, no slug fallback
        $this->addAnchorField($builder, $this->anchorSlugger);
        $this->addBackgroundField($builder);

        $builder->add('items', CollectionType::class, [
            'label' => 'label.items',
            'entry_type' => FeatureItemType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
            // Held on submit rather than by hiding EasyAdmin's "add" button, the cap being a layout limit
            'constraints' => [new Count(max: self::MAX_ITEMS, maxMessage: 'text.feature_bar_items_max')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'ui',
        ]);
    }
}
