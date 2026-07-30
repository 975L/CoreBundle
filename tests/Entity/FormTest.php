<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase
{
    public function testAddFieldSetsTheOwningSideAndIsIdempotent(): void
    {
        $form = new Form();
        $field = new FormField();

        $form->addField($field)->addField($field);

        $this->assertSame($form, $field->getForm());
        $this->assertCount(1, $form->getFields());
    }

    public function testRemoveFieldClearsTheOwningSide(): void
    {
        $form = new Form();
        $field = new FormField();
        $form->addField($field);

        $form->removeField($field);

        $this->assertNull($field->getForm());
        $this->assertCount(0, $form->getFields());
    }

    public function testGetActionConfigJsonReturnsNullWhenActionConfigIsNotSet(): void
    {
        $this->assertNull((new Form())->getActionConfigJson());
    }

    public function testActionConfigJsonRoundTripsThroughSetterAndGetter(): void
    {
        $form = (new Form())->setActionConfigJson('{"to": "contact@975l.com", "subject": "New submission"}');

        $this->assertSame(['to' => 'contact@975l.com', 'subject' => 'New submission'], $form->getActionConfig());
        $this->assertJsonStringEqualsJsonString(
            '{"to": "contact@975l.com", "subject": "New submission"}',
            (string) $form->getActionConfigJson()
        );
    }

    public function testSetActionConfigJsonWithBlankStringClearsActionConfig(): void
    {
        $form = (new Form())->setActionConfigJson('{"to": "contact@975l.com"}');

        $form->setActionConfigJson('   ');

        $this->assertNull($form->getActionConfig());
    }

    // A tampered/malformed value must not crash the admin form - it's simply discarded rather than persisted as garbage
    public function testSetActionConfigJsonWithInvalidJsonDiscardsTheValue(): void
    {
        $form = (new Form())->setActionConfigJson('not valid json');

        $this->assertNull($form->getActionConfig());
    }
}
