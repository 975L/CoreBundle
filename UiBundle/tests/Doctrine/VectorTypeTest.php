<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Doctrine;

use c975L\UiBundle\Doctrine\VectorType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

class VectorTypeTest extends TestCase
{
    private function createType(): VectorType
    {
        // Type::__construct() is private in DBAL, so reflection is the documented way to test one
        $reflection = new \ReflectionClass(VectorType::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    public function testConvertToDatabaseValueThenBackRoundTripsTheFloats(): void
    {
        $type = $this->createType();
        $platform = $this->createStub(AbstractPlatform::class);
        $floats = [1.0, -0.5, 0.0, 3.140000104904175];

        $packed = $type->convertToDatabaseValue($floats, $platform);
        $roundTripped = $type->convertToPHPValue($packed, $platform);

        $this->assertSame(16, strlen($packed));
        $this->assertEqualsWithDelta($floats, $roundTripped, 0.0001);
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $type = $this->createType();
        $platform = $this->createStub(AbstractPlatform::class);

        $this->assertNull($type->convertToDatabaseValue(null, $platform));
        $this->assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testGetSQLDeclarationUsesTheFixedDimensions(): void
    {
        $type = $this->createType();
        $platform = $this->createStub(AbstractPlatform::class);

        $this->assertSame('VECTOR(4096)', $type->getSQLDeclaration([], $platform));
    }

    public function testGetNameReturnsVector(): void
    {
        $this->assertSame('vector', $this->createType()->getName());
    }

    public function testGetBindingTypeReturnsBinary(): void
    {
        $this->assertSame(ParameterType::BINARY, $this->createType()->getBindingType());
    }

    public function testRequiresSQLCommentHintReturnsFalse(): void
    {
        $this->assertFalse($this->createType()->requiresSQLCommentHint($this->createStub(AbstractPlatform::class)));
    }
}
