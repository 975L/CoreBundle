<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Field;

use c975L\UiBundle\Field\OgImageField;
use c975L\UiBundle\Form\OgImageType;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;

// The share image an entity owns alone - SiteBundle's Page and ConfigBundle's UrlMetadata both declare their own with this field
class OgImageFieldTest extends TestCase
{
    public function testItDrawsTheShareImageUploadWidget(): void
    {
        $field = OgImageField::new('ogImage')->getAsDto();

        $this->assertSame(OgImageType::class, $field->getFormType());
        $this->assertFalse($field->getFormTypeOption('required'));
    }

    // An upload widget has nothing to show read-only, and the index lists the paths, not the pictures
    public function testItIsPickedOnTheWriteScreensAlone(): void
    {
        $field = OgImageField::new('ogImage')->getAsDto();

        $this->assertSame([Crud::PAGE_NEW, Crud::PAGE_EDIT], array_keys($field->getDisplayedOn()->all()));
    }

    // The very reason this field exists: EasyAdmin's TextConfigurator claims the two classes below and throws "can't be converted into a string" for a Media on every page it configures, forms included, so a TextField carrying this form type broke the edit screen of any row whose image had been uploaded
    public function testItIsNotOneOfTheTextFieldsWhichWouldRefuseTheMedia(): void
    {
        // The collection is what stamps a field with its own class, and that class is what a configurator picks on
        $field = new FieldCollection([OgImageField::new('ogImage')])->first();

        $this->assertSame(OgImageField::class, $field?->getFieldFqcn());
        $this->assertNotSame(TextField::class, $field?->getFieldFqcn());
        $this->assertNotSame(TextareaField::class, $field?->getFieldFqcn());
    }
}
