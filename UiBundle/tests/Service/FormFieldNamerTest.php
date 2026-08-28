<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\FormFieldNamer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class FormFieldNamerTest extends TestCase
{
    private function createNamer(): FormFieldNamer
    {
        return new FormFieldNamer(new AsciiSlugger());
    }

    private function names(Form $form): array
    {
        return array_map(static fn (FormField $field): string => $field->getName(), $form->getFields()->toArray());
    }

    public function testNameFieldsSlugifiesEachFieldLabel(): void
    {
        $form = new Form()
            ->addField(new FormField()->setLabel('Full Name'))
            ->addField(new FormField()->setLabel('Email'));

        $this->createNamer()->nameFields($form);

        $this->assertSame(['full-name', 'email'], $this->names($form));
    }

    // Two fields whose labels slugify to the same base must not collide on the same submitted key
    public function testNameFieldsDedupesCollidingSlugs(): void
    {
        $form = new Form()
            ->addField(new FormField()->setLabel('Email'))
            ->addField(new FormField()->setLabel('Email'))
            ->addField(new FormField()->setLabel('Email'));

        $this->createNamer()->nameFields($form);

        $this->assertSame(['email', 'email-2', 'email-3'], $this->names($form));
    }

    // A restricted field's name is a stable key other code looks it up by (e.g. SendEmailFormAction's "senderEmailField" config, or SiteBundle's register/reset-password-request forms reading "email"/"plainPassword" by name) - relabelling it (allowed) must not silently rename it
    public function testNameFieldsPreservesAnAlreadyNamedRestrictedField(): void
    {
        $form = new Form()
            ->addField(new FormField()->setLabel('Adresse email')->setName('email')->setRestricted(true));

        $this->createNamer()->nameFields($form);

        $this->assertSame(['email'], $this->names($form));
    }

    // A freshly seeded restricted field (no name yet) still gets one derived from its label, same as any ordinary field
    public function testNameFieldsNamesARestrictedFieldWithNoNameYet(): void
    {
        $form = new Form()
            ->addField(new FormField()->setLabel('Email')->setRestricted(true));

        $this->createNamer()->nameFields($form);

        $this->assertSame(['email'], $this->names($form));
    }

    // A restricted field's preserved name still counts toward collision avoidance for a regular field added alongside it
    public function testNameFieldsAvoidsCollidingWithARestrictedFieldsName(): void
    {
        $form = new Form()
            ->addField(new FormField()->setLabel('Email')->setName('email')->setRestricted(true))
            ->addField(new FormField()->setLabel('Email'));

        $this->createNamer()->nameFields($form);

        $this->assertSame(['email', 'email-2'], $this->names($form));
    }

    // A field and an output are both plain variables inside an expression, so one name taken by a field is not available to an output
    public function testNameFieldsNamesTheOutputsInTheSameNamespaceAsTheFields(): void
    {
        $form = new Form()
            ->addField(new FormField()->setLabel('Litres par an'))
            ->addOutput(new FormOutput()->setLabel('Litres par an')->setExpression('1'));

        $this->createNamer()->nameFields($form);

        $this->assertSame('litres-par-an', $form->getFields()->first()->getName());
        $this->assertSame('litres-par-an-2', $form->getOutputs()->first()->getName());
    }
}
