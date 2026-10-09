<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Repository\SharedBlockRepository;
use c975L\UiBundle\Service\BlockTextCollector;
use c975L\UiBundle\Service\SharedBlockTextProvider;
use PHPUnit\Framework\TestCase;

// A page's pointer holds a slug and no word: "c975l:translate:content" reaches the text of a shared block through this provider only
class SharedBlockTextProviderTest extends TestCase
{
    // Each shared block's blocks, labelled by its name so a run of the command says which one it is writing
    public function testTheBlocksOfEachSharedBlockAreHandedOver(): void
    {
        $block = new Block()->setKind('cta_band')->setData(['title' => 'Un projet de site web ?']);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 21);
        $shared = new SharedBlock()->setName('Bandeau contact')->setSlug('bandeau-contact')->addBlock($block);

        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findBy')->willReturn([$shared]);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getTranslatable')->willReturn(['title']);
        $registry->method('getTranslatableCollections')->willReturn([]);
        $registry->method('getLabel')->willReturn('Bandeau d\'appel');

        $rows = [...new SharedBlockTextProvider($repository, new BlockTextCollector($registry))->getTranslatableTexts()];

        $this->assertCount(1, $rows);
        $this->assertSame(Translation::OWNER_BLOCK, $rows[0]['owner']);
        $this->assertSame(21, $rows[0]['ownerId']);
        $this->assertSame('Un projet de site web ?', $rows[0]['source']);
        $this->assertSame('Bloc partagé Bandeau contact / Bandeau d\'appel', $rows[0]['label']);
    }
}
