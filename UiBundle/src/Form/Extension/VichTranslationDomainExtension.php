<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

// Pins the two labels VichUploaderBundle ships its own translations for - the "delete?" checkbox and the "download" link - to the domain those translations actually live in. Left alone, both options default to null, meaning "inherit the domain of the form around me": inside the admin that form is EasyAdmin's, built with the dashboard's translation domain (see ConfigBundle's DashboardController), so the keys were looked up in a domain that never held them and every upload field rendered "vich_uploader.form_label.delete_confirm" as-is
// Written as an extension rather than as two more entries in VichImageOptions: half the upload fields of the ecosystem pass hand-written options (GalleryMedia, Font, Media...) and would each have had to remember them. Both types are listed because VichImageType only extends VichFileType in PHP, not through getParent() - an extension of the latter never reaches the former
class VichTranslationDomainExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [VichFileType::class, VichImageType::class];
    }

    // Defaults only: a field naming its own domain, and the download label resolved from the stored originalName (domain false, plain text), both still win
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'delete_label_translation_domain' => 'messages',
            'download_label_translation_domain' => 'messages',
        ]);
    }
}
