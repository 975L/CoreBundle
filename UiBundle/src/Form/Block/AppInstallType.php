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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The "data" sub-form of the "app_install" kind: the app itself is the site's config (see PwaController), the block only says what it is introduced with and whether its store listing is linked
class AppInstallType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'label.text',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            // Ticked by hand rather than read off "ui-pwa-android-package": the package is set before the listing is public, and a link to it would lead nowhere until then
            ->add('playStore', CheckboxType::class, [
                'label' => 'label.app_install_play_store',
                'help' => 'label.app_install_play_store_help',
                'required' => false,
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
