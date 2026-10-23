<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Util;

use c975L\UiBundle\Form\Util\PositionSorter;
use PHPUnit\Framework\TestCase;

class PositionSorterTest extends TestCase
{
    // The order the rows were dragged to is the order returned, the position itself dropped
    public function testSortOrdersByPositionAndDropsIt(): void
    {
        $rows = PositionSorter::sort([
            ['network' => 'facebook', 'position' => '1'],
            ['network' => 'youtube', 'position' => '0'],
        ]);

        $this->assertSame([['network' => 'youtube'], ['network' => 'facebook']], $rows);
    }

    // A single row without a position keeps the submitted order for all of them, the positions the others carry dropped too
    public function testSortKeepsTheSubmittedOrderWhenARowHasNoPosition(): void
    {
        $rows = PositionSorter::sort([
            ['network' => 'youtube', 'position' => '5'],
            ['network' => 'facebook', 'position' => ''],
            ['network' => 'instagram', 'position' => null],
        ]);

        $this->assertSame([['network' => 'youtube'], ['network' => 'facebook'], ['network' => 'instagram']], $rows);
    }

    // Two rows on the same position keep the order they were submitted in
    public function testSortKeepsTheSubmittedOrderOfTies(): void
    {
        $rows = PositionSorter::sort([
            ['network' => 'facebook', 'position' => '1'],
            ['network' => 'instagram', 'position' => '1'],
            ['network' => 'youtube', 'position' => '0'],
        ]);

        $this->assertSame(['youtube', 'facebook', 'instagram'], array_column($rows, 'network'));
    }

    // A forged non-array entry is passed through untouched, for the form's validation to reject rather than a TypeError
    public function testSortPassesNonArrayEntriesThrough(): void
    {
        $rows = PositionSorter::sort([
            ['network' => 'facebook', 'position' => '1'],
            'x',
        ]);

        $this->assertSame([['network' => 'facebook'], 'x'], $rows);
    }
}
