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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

// Embeds a single Media upload (c975L\UiBundle\Entity\Media, role=null) for the share image an entity owns alone - SiteBundle's Page::$ogImage and ConfigBundle's UrlMetadata::$ogImage, both of which draw this very form
class OgImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', VichImageType::class, ['label' => false] + VichImageOptions::default('2M'));

        // What the image shows, for whoever is read the share rather than shown it - "og:image:alt" in both layouts. The very field the media library gives every other image (see MediaUploadType), under the same label
        $builder->add('alt', TextType::class, [
            'label' => 'label.alt_text',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Media::class,
            'translation_domain' => 'ui',
        ]);
    }
}
