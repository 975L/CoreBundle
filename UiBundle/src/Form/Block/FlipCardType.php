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
use c975L\UiBundle\Form\BlockCardSizeChoiceType;
use c975L\UiBundle\Form\BlockClassChoiceType;
use c975L\UiBundle\Form\BlockRadiusChoiceType;
use c975L\UiBundle\Form\BlockShadowChoiceType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A two-faced card: the front's fields are the generic title/content pair every other kind uses, the back's are their "back" counterparts. Both faces' images come from this block's medias (media_multi_upload on its tag): the first is the front's, the second the back's - see templates/blocks/FlipCard.html.twig.
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
            // The two faces are told apart on the edit screen the way they are on the page: consecutive fields sharing one "data-block-fieldset" are wrapped in a fieldset carrying that legend - see templates/form/block.html.twig. Without it the six fields read as one flat run, and "Titre" / "Titre du verso" are all that says which face is being filled in
            ->add('title', TextType::class, [
                'label' => 'label.front_title',
                'required' => false,
                'row_attr' => ['data-block-fieldset' => 'label.face_front'],
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.front_content',
                'required' => false,
                'row_attr' => ['data-block-fieldset' => 'label.face_front'],
            ])
            ->add('backTitle', TextType::class, [
                'label' => 'label.back_title',
                'required' => false,
                'row_attr' => ['data-block-fieldset' => 'label.face_back'],
            ])
            ->add('backContent', TrixEditorType::class, [
                'label' => 'label.back_content',
                'required' => false,
                'row_attr' => ['data-block-fieldset' => 'label.face_back'],
            ])
            // One level for both faces: they are the same card seen from two sides, so heading twice at two different depths would read as two unrelated sections to anything walking the outline. Outside either fieldset for that very reason, with the other whole-card fields
            ->add('level', ChoiceType::class, [
                'label' => 'label.title_level',
                'choices' => [
                    'h2' => 'h2',
                    'h3' => 'h3',
                    'h4' => 'h4',
                ],
            ])
            // The very list a slider offers, reused rather than restated - the same reuse SiteBundle's
            // ArticlesSliderType already makes of it. Its own help text is not reused though: a slider's ratio crops an image, where this one is a floor under the card's box, not a crop (see sass/_flip-card.scss)
            ->add('ratio', ChoiceType::class, [
                'label' => 'label.ratio',
                'help' => 'label.flip_card_ratio_help',
                'choices' => SliderType::RATIO_CHOICES,
            ])
            // Both faces at once, being the two sides of one object: the corner and the lift are what makes it that object, and a turn changing either would read as a swap rather than as a rotation
            ->add('size', BlockCardSizeChoiceType::class)
            ->add('radius', BlockRadiusChoiceType::class)
            ->add('shadow', BlockShadowChoiceType::class)
            ->add('class', BlockClassChoiceType::class)
            ->add('accent', BlockAccentChoiceType::class);
    }

    // The block's own media field is added by BlockType, next to this sub-form rather than inside it, so it renders under everything by default - two faces away from the face its first image is shown on. Naming a field here hoists it right after that one (see templates/form/block.html.twig), which puts both illustrations inside the "recto" fieldset, where the help text explaining their order is read
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['media_after'] = 'content';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
