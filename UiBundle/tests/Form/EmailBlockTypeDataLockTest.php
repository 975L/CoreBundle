<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Form\EmailBlockType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

/**
 * The other half of "a data block moves but is never deleted".
 *
 * EmailTemplateCrudController puts back a data block a submission dropped, but that guard only recognises a block by
 * the kind it is: retype a "slot" into a "text" and there is nothing left for it to protect - the order's lines are
 * gone and the block is still there, so nothing looks wrong. The kind and the slot name are locked here for that
 * reason, and locked on the saved block itself rather than in the page.
 */
class EmailBlockTypeDataLockTest extends TestCase
{
    // What actually matters, the disabling below being only the mechanism: a submission naming another kind changes nothing
    public function testASubmittedKindCannotReplaceASavedSlot(): void
    {
        $block = $this->block(EmailBlock::TYPE_SLOT, 'items', 7);
        $form = $this->form($block);

        $form->submit([
            'type' => EmailBlock::TYPE_TEXT,
            'label' => 'anything',
            'content' => 'Some wording of mine',
            'position' => '7',
        ]);

        $this->assertSame(EmailBlock::TYPE_SLOT, $block->getType());
        $this->assertSame('items', $block->getLabel());

        // What is not locked stays the admin's, so the block is still theirs to move and to write around
        $this->assertSame('Some wording of mine', $block->getContent());
    }

    public function testASavedDataBlockHasItsKindAndSlotNameLocked(): void
    {
        foreach (EmailBlock::DATA_TYPES as $type) {
            $form = $this->form($this->block($type, 'items', 0));

            $this->assertTrue($form->get('type')->isDisabled(), $type);
            $this->assertTrue($form->get('label')->isDisabled(), $type);
        }
    }

    // Wording blocks are the admin's entirely, kind included
    public function testASavedWordingBlockIsLeftAlone(): void
    {
        $form = $this->form($this->block(EmailBlock::TYPE_TEXT, null, 0));

        $this->assertFalse($form->get('type')->isDisabled());
        $this->assertFalse($form->get('label')->isDisabled());
    }

    // A row just added is not saved yet, and the choice list still offers both kinds - that is how an admin places a slot a bundle update has started declaring
    public function testANewBlockCanStillBeMadeASlot(): void
    {
        $form = $this->form(null);

        $this->assertFalse($form->get('type')->isDisabled());
        $this->assertFalse($form->get('label')->isDisabled());
    }

    private function form(?EmailBlock $block): FormInterface
    {
        return Forms::createFormFactory()->create(EmailBlockType::class, $block);
    }

    private function block(string $type, ?string $label, int $position): EmailBlock
    {
        $block = new EmailBlock()->setType($type)->setLabel($label)->setPosition($position);

        // Saved is what the lock keys on, and an id is the only thing that says so
        new \ReflectionProperty(EmailBlock::class, 'id')->setValue($block, 12);

        return $block;
    }
}
