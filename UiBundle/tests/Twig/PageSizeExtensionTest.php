<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Management\PaginatorPageSize;
use c975L\UiBundle\Twig\PageSizeExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class PageSizeExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersPageSizesFunction(): void
    {
        $functions = new AttributeExtension(PageSizeExtension::class)->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('page_sizes', $functions[0]->getName());
    }

    // A list of its own would let the paginator offer a size PaginatorPageSize then refuses, sending the admin back to 20 rows on every click
    public function testTheSizesOfferedAreTheOnesResolveAccepts(): void
    {
        $this->assertSame(PaginatorPageSize::SIZES, new PageSizeExtension()->getSizes());
    }
}
