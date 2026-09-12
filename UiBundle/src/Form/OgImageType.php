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
use c975L\UiBundle\Validator\FixedIconFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

// Embeds a single Media upload (c975L\UiBundle\Entity\Media, role=null) for the share image an entity owns alone - SiteBundle's Page::$ogImage and ConfigBundle's UrlMetadata::$ogImage, both of which draw this very form
class OgImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', VichImageType::class, ['label' => false] + VichImageOptions::default('2M'));

        // Marked on SUBMIT, ahead of the validation POST_SUBMIT runs, so the SVG check, the webp name and the conversion all see an og-image (see Media::markAsOgImage())
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $event->getData()?->markAsOgImage();
        });

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
            // Stated on the form rather than left to the entity: neither Page nor UrlMetadata cascades validation into its og-image
            'constraints' => [new FixedIconFormat()],
        ]);
    }
}
