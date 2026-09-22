<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Repository\AiSearchChunkRepository;
use PHPUnit\Framework\TestCase;

// The stemming MariaDB's FULLTEXT index doesn't do: "contacter" has to find the "Contact" page
class AiSearchChunkRepositoryTest extends TestCase
{
    public function testAQuestionKeepsTheStemsOfTheWordsThatSaySomething(): void
    {
        $this->assertSame('conta*', AiSearchChunkRepository::booleanQuery('Comment vous contacter ?'));
        $this->assertSame('tarif* site* vitri*', AiSearchChunkRepository::booleanQuery('Quels sont vos tarifs pour un site vitrine ?'));
    }

    // "+", "-", quotes and parentheses mean something in BOOLEAN MODE, and a visitor's question is no query
    public function testTheOperatorsAVisitorTypesAreDropped(): void
    {
        $this->assertSame('horai* samed*', AiSearchChunkRepository::booleanQuery('+horaires -"samedi" (horaires)'));
    }

    public function testAQuestionSayingNothingLeavesNothingToLookFor(): void
    {
        $this->assertSame('', AiSearchChunkRepository::booleanQuery('Comment vous ?'));
    }
}
