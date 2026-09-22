<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The "data" sub-form of the "ai_search" kind: the search itself is the site's config (see AiSiteSearch), the block only says what the field is introduced with
class AiSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('placeholder', TextType::class, [
                'label' => 'label.ai_search_placeholder',
                'help' => 'label.ai_search_placeholder_help',
                'required' => false,
            ])
            // Questions offered under the field, one per line: a visitor facing an empty box rarely knows what it can be asked
            ->add('suggestions', TextareaType::class, [
                'label' => 'label.ai_search_suggestions',
                'help' => 'label.ai_search_suggestions_help',
                'required' => false,
                'attr' => ['rows' => 3],
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
