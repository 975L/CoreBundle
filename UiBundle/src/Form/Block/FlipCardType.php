<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\BlockAccentChoiceType;
use c975L\UiBundle\Form\BlockClassChoiceType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A two-faced card: the front's fields are the generic title/content pair every other kind uses, the back's
// are their "back" counterparts. Both faces' images come from this block's medias (media_multi_upload on its
// tag): the first is the front's, the second the back's - see templates/blocks/FlipCard.html.twig.
// No url/button pair on either face on purpose: both contents are Trix editors, which already write links
class FlipCardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', TextType::class, [
                'label' => 'label.identifier',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.front_title',
                'required' => false,
            ])
            // One level for both faces: they are the same card seen from two sides, so heading twice at two
            // different depths would read as two unrelated sections to anything walking the outline
            ->add('level', ChoiceType::class, [
                'label' => 'label.title_level',
                'choices' => [
                    'h2' => 'h2',
                    'h3' => 'h3',
                    'h4' => 'h4',
                ],
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.front_content',
                'required' => false,
            ])
            ->add('backTitle', TextType::class, [
                'label' => 'label.back_title',
                'required' => false,
            ])
            ->add('backContent', TrixEditorType::class, [
                'label' => 'label.back_content',
                'required' => false,
            ])
            // The very list a slider offers, reused rather than restated - the same reuse SiteBundle's
            // ArticlesSliderType already makes of it. Its own help text is not reused though: a slider's ratio
            // crops an image, where this one is a floor under the card's box, not a crop (see sass/_flip-card.scss)
            ->add('ratio', ChoiceType::class, [
                'label' => 'label.ratio',
                'help' => 'label.flip_card_ratio_help',
                'choices' => SliderType::RATIO_CHOICES,
            ])
            ->add('class', BlockClassChoiceType::class)
            ->add('accent', BlockAccentChoiceType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
