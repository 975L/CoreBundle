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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Deliberately field-less (same reasoning as VideoType): an "audio" block is entirely defined by the file uploaded as its media (see the "ui.block.audio" tag in config/services.yaml), format included - blocks/Audio.html.twig reads both back from the Media itself, so there's nothing left to ask the editor. Kept as a real FormType because every block kind is registered with one
class AudioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'ui',
        ]);
    }
}
