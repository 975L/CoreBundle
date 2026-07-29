<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\UiBundle\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

// Maps a PHP float[] to MariaDB 11.7+'s native VECTOR(n), stored as the raw little-endian float32 bytes it expects
class VectorType extends Type
{
    public const NAME = 'vector';

    // A constant, Doctrine instantiating custom types with no constructor arguments; another model needs a subclass
    public const DIMENSIONS = 4096;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VECTOR(' . self::DIMENSIONS . ')';
    }

    /**
     * @return float[]|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return null === $value ? null : self::unpack($value);
    }

    /**
     * @param float[]|null $value
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return null === $value ? null : self::pack($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    // "vector" is a real native SQL type name, so introspection alone identifies the column
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return false;
    }

    // The packed bytes are arbitrary binary: STRING would let charset conversion mangle them
    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

    // Static so a repository can pack a query vector the same way for a raw VEC_DISTANCE_COSINE() query
    /** @param float[] $floats */
    public static function pack(array $floats): string
    {
        return pack('g*', ...$floats);
    }

    /** @return float[] */
    public static function unpack(string $bytes): array
    {
        return array_values(unpack('g*', $bytes));
    }
}
