<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use PHPUnit\Framework\TestCase;

class EmailTemplateTest extends TestCase
{
    public function testAddBlockSetsTheOwningSideAndIsIdempotent(): void
    {
        $emailTemplate = new EmailTemplate();
        $block = new EmailBlock();

        $emailTemplate->addBlock($block)->addBlock($block);

        $this->assertSame($emailTemplate, $block->getEmailTemplate());
        $this->assertCount(1, $emailTemplate->getBlocks());
    }

    public function testRemoveBlockClearsTheOwningSide(): void
    {
        $emailTemplate = new EmailTemplate();
        $block = new EmailBlock();
        $emailTemplate->addBlock($block);

        $emailTemplate->removeBlock($block);

        $this->assertNull($block->getEmailTemplate());
        $this->assertCount(0, $emailTemplate->getBlocks());
    }

    public function testToStringReturnsName(): void
    {
        $emailTemplate = new EmailTemplate()->setName('contact_notification');

        $this->assertSame('contact_notification', (string) $emailTemplate);
    }
}
