<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\BoolExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BoolExtensionTest extends TestCase
{
    #[DataProvider('provideFalsyValues')]
    public function testToBoolReturnsFalseForFalsyValues(mixed $value): void
    {
        $extension = new BoolExtension();

        $this->assertFalse($extension->toBool($value));
    }

    public static function provideFalsyValues(): iterable
    {
        yield 'boolean false' => [false];
        yield 'string "false"' => ['false'];
        yield 'string zero' => ['0'];
        yield 'integer zero' => [0];
        yield 'empty string' => [''];
    }

    #[DataProvider('provideTruthyValues')]
    public function testToBoolReturnsTrueForEverythingElse(mixed $value): void
    {
        $extension = new BoolExtension();

        $this->assertTrue($extension->toBool($value));
    }

    public static function provideTruthyValues(): iterable
    {
        yield 'boolean true' => [true];
        yield 'string "true"' => ['true'];
        yield 'string "1"' => ['1'];
        yield 'integer one' => [1];
        yield 'non-empty string' => ['yes'];
        yield 'null' => [null];
    }

    public function testGetFiltersRegistersToBoolFilter(): void
    {
        $extension = new BoolExtension();
        $filters = $extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('to_bool', $filters[0]->getName());
    }
}
