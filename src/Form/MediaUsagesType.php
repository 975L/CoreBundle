<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Read-only "used in" summary shown on the Media edit form (list of blocks/pages/roles using this Media, each linking back to where it's edited). Unmapped: EasyAdmin's own formatValue()+ setTemplatePath() mechanism only applies to Index/Detail rendering, never to New/Edit forms, so a plain Field::new('id') there falls back to rendering the raw id as an editable number input - this type replaces that with the real, non-editable content (see media_usages_theme.html.twig)
class MediaUsagesType extends AbstractType
{
    public function __construct(private readonly MediaUsageRegistry $mediaUsageRegistry)
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $media = $form->getParent()?->getData();

        $view->vars['mediaId'] = $media instanceof Media ? $media->getId() : null;
        $view->vars['usages'] = $media instanceof Media
            ? $this->mediaUsageRegistry->getUsages([$media])[$media->getId()] ?? []
            : [];
    }

    // Without a parent, this type gets none of FormType's base machinery (block-prefix fallback chain, "compound" default...) and renders nothing at all - same reason IconPickerType extends TextType instead of leaving getParent() as the AbstractType default (null)
    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'media_usages';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
        ]);
    }
}
