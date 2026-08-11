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
        $this->assertNull(new Form()->getActionConfigJson());
    }

    public function testActionConfigJsonRoundTripsThroughSetterAndGetter(): void
    {
        $form = new Form()->setActionConfigJson('{"to": "contact@975l.com", "subject": "New submission"}');

        $this->assertSame(['to' => 'contact@975l.com', 'subject' => 'New submission'], $form->getActionConfig());
        $this->assertJsonStringEqualsJsonString(
            '{"to": "contact@975l.com", "subject": "New submission"}',
            (string) $form->getActionConfigJson()
        );
    }

    public function testSetActionConfigJsonWithBlankStringClearsActionConfig(): void
    {
        $form = new Form()->setActionConfigJson('{"to": "contact@975l.com"}');

        $form->setActionConfigJson('   ');

        $this->assertNull($form->getActionConfig());
    }

    // A tampered/malformed value must not crash the admin form - it's simply discarded rather than persisted as garbage
    public function testSetActionConfigJsonWithInvalidJsonDiscardsTheValue(): void
    {
        $form = new Form()->setActionConfigJson('not valid json');

        $this->assertNull($form->getActionConfig());
    }

    public function testLinksAreStoredInTheActionConfig(): void
    {
        $form = new Form()->setLinks([['label' => 'Sign in', 'url' => '/login']]);

        $this->assertSame([['label' => 'Sign in', 'url' => '/login']], $form->getLinks());
        $this->assertSame(['links' => [['label' => 'Sign in', 'url' => '/login']]], $form->getActionConfig());
    }

    public function testGetLinksReturnsAnEmptyArrayWhenNoneAreSet(): void
    {
        $this->assertSame([], new Form()->getLinks());
    }

    // The collection's "+ Add" row submits an empty pair when left untouched, and a half-filled one is useless anyway
    public function testSetLinksDropsIncompleteEntriesAndReindexes(): void
    {
        $form = new Form()->setLinks([
            ['label' => '  Sign in  ', 'url' => ' /login '],
            ['label' => '', 'url' => ''],
            ['label' => 'No address', 'url' => '   '],
        ]);

        $this->assertSame([['label' => 'Sign in', 'url' => '/login']], $form->getLinks());
    }

    // Clearing the collection leaves the rest of the action config untouched, and records the emptying rather than dropping the key - that is what FormSeeder reads to tell an admin's own clearing from a Form that was never seeded any link
    public function testSetLinksWithNothingLeftEmptiesOnlyTheLinksKey(): void
    {
        $form = new Form()
            ->setActionConfig(['to' => 'contact@975l.com'])
            ->setLinks([['label' => 'Sign in', 'url' => '/login']]);

        $form->setLinks([]);

        $this->assertSame(['to' => 'contact@975l.com', 'links' => []], $form->getActionConfig());
        $this->assertSame([], $form->getLinks());
    }

    // ... and the emptied key survives a save of the raw JSON textarea, which never carries it
    public function testTheEmptiedLinksSurviveTheRawJsonTextarea(): void
    {
        $form = new Form()->setLinks([['label' => 'Sign in', 'url' => '/login']]);
        $form->setLinks([]);

        $form->setActionConfigJson('{"to": "contact@975l.com"}');

        $this->assertSame(['to' => 'contact@975l.com', 'links' => []], $form->getActionConfig());
    }

    // Links have their own collection editor, so they never show up in the raw JSON textarea next to it
    public function testActionConfigJsonLeavesTheLinksOut(): void
    {
        $form = new Form()
            ->setActionConfig(['to' => 'contact@975l.com'])
            ->setLinks([['label' => 'Sign in', 'url' => '/login']]);

        $this->assertJsonStringEqualsJsonString('{"to": "contact@975l.com"}', (string) $form->getActionConfigJson());
    }

    public function testActionConfigJsonOnNothingButLinksIsNull(): void
    {
        $form = new Form()->setLinks([['label' => 'Sign in', 'url' => '/login']]);

        $this->assertNull($form->getActionConfigJson());
    }

    // Since the textarea never carries them, saving it must not be what drops them
    public function testSavingTheRawJsonKeepsTheLinks(): void
    {
        $form = new Form()->setLinks([['label' => 'Sign in', 'url' => '/login']]);

        $form->setActionConfigJson('{"to": "contact@975l.com"}');

        $this->assertSame([['label' => 'Sign in', 'url' => '/login']], $form->getLinks());
        $this->assertSame('contact@975l.com', $form->getActionConfig()['to']);
    }

    public function testClearingTheRawJsonKeepsTheLinks(): void
    {
        $form = new Form()
            ->setActionConfig(['to' => 'contact@975l.com'])
            ->setLinks([['label' => 'Sign in', 'url' => '/login']]);

        $form->setActionConfigJson('');

        $this->assertSame([['label' => 'Sign in', 'url' => '/login']], $form->getLinks());
    }
}
