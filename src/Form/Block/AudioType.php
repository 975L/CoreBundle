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
use c975L\UiBundle\Form\BlockClassChoiceType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The file and its format both come from the media uploaded on the block (see the "ui.block.audio" tag in config/services.yaml), so this form only carries the same title/description/class display fields as VideoType/VideoIframeType - no width/height, an <audio> element has no such attributes
class AudioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label'    => 'label.title',
                'required' => false,
            ])
            ->add('description', TrixEditorType::class, [
                'label'    => 'label.description',
                'required' => false,
            ])
            ->add('class', BlockClassChoiceType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'ui',
        ]);
    }
}
