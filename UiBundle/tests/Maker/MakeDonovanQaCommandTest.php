<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Maker;

use c975L\UiBundle\Maker\MakeDonovanQaCommand;
use PHPUnit\Framework\TestCase;

// Covers the deterministic, IO-free parts only; generate() needs a real booted app to exercise
class MakeDonovanQaCommandTest extends TestCase
{
    public function testCommandNameMatchesCustomC975lConvention(): void
    {
        $this->assertSame('c975l:ui:donovan-qa:create', MakeDonovanQaCommand::getCommandName());
    }

    public function testCommandDescriptionIsNotEmpty(): void
    {
        $this->assertNotSame('', MakeDonovanQaCommand::getCommandDescription());
    }
}
