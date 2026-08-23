<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Service\EmailTemplateFactory;
use PHPUnit\Framework\TestCase;

// The one place a bundle's declaration becomes an EmailTemplate - shared by the seeder, which persists it, and the renderer, which throws it away
class EmailTemplateFactoryTest extends TestCase
{
    public function testTheTemplateCarriesTheNameAndTheLanguageItWasBuiltFor(): void
    {
        $emailTemplate = new EmailTemplateFactory()->build('password_reset', 'es', []);

        $this->assertSame('password_reset', $emailTemplate->getName());
        $this->assertSame('es', $emailTemplate->getLocale());
    }

    // Restricted: the name and the block structure are the bundle's, only the wording is the admin's to change
    public function testTheTemplateIsBornRestricted(): void
    {
        $this->assertTrue(new EmailTemplateFactory()->build('password_reset', 'fr', [])->isRestricted());
    }

    public function testEachTupleBecomesABlockInTheOrderItWasDeclared(): void
    {
        $emailTemplate = new EmailTemplateFactory()->build('shop_order', 'fr', [
            [EmailBlock::TYPE_HEADING, 'Merci', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'Votre commande', null, null],
        ]);

        $blocks = $emailTemplate->getBlocks()->toArray();

        $this->assertCount(2, $blocks);
        $this->assertSame(EmailBlock::TYPE_HEADING, $blocks[0]->getType());
        $this->assertSame('Merci', $blocks[0]->getHeading());
        $this->assertSame(EmailBlock::LEVEL_H1, $blocks[0]->getLevel());
        $this->assertSame(0, $blocks[0]->getPosition());
        $this->assertSame('Votre commande', $blocks[1]->getContent());
        $this->assertSame(1, $blocks[1]->getPosition());
    }

    // A data block is offered once and only once: recorded here so a later backfill never puts back one an admin removed
    public function testADataBlockIsRecordedAsAlreadyOffered(): void
    {
        $emailTemplate = new EmailTemplateFactory()->build('shop_order', 'fr', [
            [EmailBlock::TYPE_SLOT, null, null, null, 'order_lines', null],
            [EmailBlock::TYPE_FIELDS_TABLE, null, null, null, 'fields', null],
        ]);

        $this->assertSame(['order_lines', 'fields'], $emailTemplate->getSeededBlocks());
    }

    // Decoration has no identity to match a backfill on, so it is never recorded
    public function testAnOrdinaryBlockIsNotRecordedAsOffered(): void
    {
        $emailTemplate = new EmailTemplateFactory()->build('shop_order', 'fr', [
            [EmailBlock::TYPE_TEXT, null, null, 'Bonjour', 'intro', null],
            [EmailBlock::TYPE_DIVIDER, null, null, null, null, null],
        ]);

        $this->assertSame([], $emailTemplate->getSeededBlocks());
    }

    // A slot with no name cannot be matched on later either, so it is left out rather than recorded as ""
    public function testAnUnnamedDataBlockIsNotRecorded(): void
    {
        $emailTemplate = new EmailTemplateFactory()->build('shop_order', 'fr', [
            [EmailBlock::TYPE_SLOT, null, null, null, null, null],
        ]);

        $this->assertSame([], $emailTemplate->getSeededBlocks());
    }

    public function testADeclarationWithNoBlockBuildsAnEmptyTemplate(): void
    {
        $emailTemplate = new EmailTemplateFactory()->build('empty', 'fr', []);

        $this->assertCount(0, $emailTemplate->getBlocks());
        $this->assertSame([], $emailTemplate->getSeededBlocks());
    }
}
