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
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\Media;
use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;

class ColumnLengthConstraintTest extends TestCase
{
    // Every column an editor types into, with the entity it belongs to
    public static function editedColumns(): iterable
    {
        foreach (['label', 'placeholder', 'url', 'defaultValue'] as $property) {
            yield 'FormField::' . $property => [FormField::class, $property];
        }
        foreach (['alt', 'label', 'name', 'width', 'height', 'credits', 'url'] as $property) {
            yield 'Media::' . $property => [Media::class, $property];
        }
        foreach (['heading', 'alt', 'label', 'url'] as $property) {
            yield 'EmailBlock::' . $property => [EmailBlock::class, $property];
        }
    }

    // A text longer than its column is refused on the screen rather than failing the flush with "Data too long"
    #[DataProvider('editedColumns')]
    public function testTheLengthConstraintMatchesTheColumn(string $class, string $property): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $column = $reflection->getAttributes(Column::class)[0]->newInstance();
        $lengths = array_map(static fn (\ReflectionAttribute $attribute): ?int => $attribute->newInstance()->max, $reflection->getAttributes(Length::class));

        $this->assertNotNull($column->length);
        $this->assertSame([$column->length], $lengths);
    }
}
