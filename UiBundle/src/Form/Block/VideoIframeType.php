<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\BlockClassChoiceType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoIframeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('src', UrlType::class, [
                'label' => 'label.video_url',
            ])
            // Only rewrites the URL for a recognized YouTube host - see BlockVideoNoCookieListener, which performs the actual rewrite once on save, so the stored src (and the render template) never need to know about this checkbox
            ->add('noCookie', CheckboxType::class, [
                'label' => 'label.video_no_cookie',
                'required' => false,
            ])
            // A one-shot action rather than a stored preference (see VideoPosterImporter, which clears it once done): the still is copied to this site's own files, so the editor keeps the last word on it and a still that changed on the platform is re-imported by ticking the box again
            // Always offered rather than only for a src already known to be a YouTube one: the box has to be tickable while the url is being pasted for the first time, and it is a no-op for any platform serving no guessable still
            ->add('importPoster', CheckboxType::class, [
                'label' => 'label.video_import_poster',
                'help' => 'label.video_import_poster_help',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('description', TrixEditorType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
            ->add('width', TextType::class, [
                'label' => 'label.width',
                'required' => false,
            ])
            ->add('height', TextType::class, [
                'label' => 'label.height',
                'required' => false,
            ])
            ->add('class', BlockClassChoiceType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
