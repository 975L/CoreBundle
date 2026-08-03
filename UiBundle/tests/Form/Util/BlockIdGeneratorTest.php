<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Util;

use c975L\UiBundle\Form\Util\BlockIdGenerator;
use PHPUnit\Framework\TestCase;

class BlockIdGeneratorTest extends TestCase
{
    public function testGeneratePrefixesTheGivenPrefix(): void
    {
        $this->assertMatchesRegularExpression('/^slider-[0-9a-f]{8}$/', BlockIdGenerator::generate('slider'));
    }

    public function testGenerateSupportsAHyphenatedPrefix(): void
    {
        $this->assertMatchesRegularExpression('/^image-compare-[0-9a-f]{8}$/', BlockIdGenerator::generate('image-compare'));
    }

    public function testGenerateReturnsADifferentIdOnEachCall(): void
    {
        $this->assertNotSame(BlockIdGenerator::generate('slider'), BlockIdGenerator::generate('slider'));
    }
}
