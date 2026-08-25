<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Field;

use c975L\UiBundle\Form\OgImageType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

// The share image an entity owns alone - SiteBundle's Page::$ogImage and ConfigBundle's UrlMetadata::$ogImage - drawn by the upload widget OgImageType renders. A field of its own rather than one of EasyAdmin's: an untyped field resolves off the Doctrine mapping and an association becomes an AssociationField, force-injecting options OgImageType doesn't declare, while a TextField refuses the Media outright ("can't be converted into a string", thrown by its configurator on every page, forms included, as soon as an image is set)
final class OgImageField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface | string | bool | null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(OgImageType::class)
            ->setFormTypeOption('required', false)
            // Same width TextField gives itself, the widget having been drawn at that width until now
            ->setDefaultColumns('col-md-6 col-xxl-5')
            // An upload widget has nothing to show read-only, and the index lists the paths, not the pictures
            ->onlyOnForms()
        ;
    }
}
