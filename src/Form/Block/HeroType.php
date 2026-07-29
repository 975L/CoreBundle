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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HeroType extends AbstractType
{
    use HasAnchorFieldTrait;
    use HasBackgroundFieldTrait;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $builder
            ->add('badge', TextType::class, [
                'label' => 'label.badge',
                'required' => false,
            ])
            // TrixEditorType (not a plain TextType) so an editor can emphasize a word (<em>) the same way the reference design highlights it in red - see blocks/Hero.html.twig / _hero__title em
            ->add('title', TrixEditorType::class, [
                'label' => 'label.title',
            ])
            // h2 for a hero sitting under a page template that already prints its own h1
            ->add('titleLevel', ChoiceType::class, [
                'label' => 'label.hero_title_level',
                'help' => 'label.hero_title_level_help',
                'choices' => ['h1' => 'h1', 'h2' => 'h2'],
                'placeholder' => false,
            ])
            // TrixEditorType too, same reason as "title" above - a few words can be emphasized in the subtitle
            ->add('subtitle', TrixEditorType::class, [
                'label' => 'label.subtitle',
                'required' => false,
            ])
            ->add('hasBackgroundImage', CheckboxType::class, [
                'label' => 'label.hero_background_image',
                'help' => 'label.hero_background_image_help',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ]);

        // Right after the background image it competes with, a hero showing one ignoring the flat
        $this->addBackgroundField($builder);

        $builder
            // Optional: a hero with no call to action is legitimate, the template dropping the whole row
            ->add('primaryLabel', TextType::class, [
                'label' => 'label.primary_label',
                'required' => false,
            ])
            ->add('primaryUrl', TextType::class, [
                'label' => 'label.primary_url',
                'required' => false,
            ])
            ->add('secondaryLabel', TextType::class, [
                'label' => 'label.secondary_label',
                'required' => false,
            ])
            ->add('secondaryUrl', TextType::class, [
                'label' => 'label.secondary_url',
                'required' => false,
            ])
            ->add('statValue', TextType::class, [
                'label' => 'label.stat_value',
                'required' => false,
            ])
            ->add('statLabel', TextType::class, [
                'label' => 'label.stat_label',
                'required' => false,
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
