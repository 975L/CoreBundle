<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ComparisonColumnType;
use c975L\UiBundle\Form\Block\ComparisonRowType;
use c975L\UiBundle\Form\Block\ComparisonTableType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ComparisonTableTypeTest extends TestCase
{
    // Columns and rows as collections, the head, the highlighted column and the anchor optional
    public function testBuildFormAddsColumnsRowsAndOptionalHead(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new ComparisonTableType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        $this->assertSame(CollectionType::class, $added['columns']['type']);
        $this->assertSame(ComparisonColumnType::class, $added['columns']['options']['entry_type']);
        $this->assertSame(CollectionType::class, $added['rows']['type']);
        $this->assertSame(ComparisonRowType::class, $added['rows']['options']['entry_type']);
        foreach (['eyebrow', 'title', 'firstColumn', 'highlight'] as $field) {
            $this->assertFalse($added[$field]['options']['required'], $field);
        }
        $this->assertArrayHasKey('anchor', $added);
    }
}
