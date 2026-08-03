<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity\Trait;

use c975L\ConfigBundle\Contract\UserInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class VichMediaTraitTest extends TestCase
{
    public function testToStringReturnsName(): void
    {
        $media = (new VichMediaTraitStub())->setName('logo.webp');

        $this->assertSame('logo.webp', (string) $media);
    }

    public function testEqualsReturnsFalseForAnUnrelatedObject(): void
    {
        $media = new VichMediaTraitStub();

        $this->assertFalse($media->equals(new \stdClass()));
    }

    public function testEqualsComparesByIdWhenBothArePersisted(): void
    {
        $media = (new VichMediaTraitStub())->setId(1);
        $other = (new VichMediaTraitStub())->setId(1);

        $this->assertTrue($media->equals($other));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $media = (new VichMediaTraitStub())->setId(1);
        $other = (new VichMediaTraitStub())->setId(2);

        $this->assertFalse($media->equals($other));
    }

    // Neither side is persisted yet (both ids null) - falls back to comparing names
    public function testEqualsFallsBackToNameWhenNeitherIsPersisted(): void
    {
        $media = (new VichMediaTraitStub())->setName('logo.webp');
        $other = (new VichMediaTraitStub())->setName('logo.webp');

        $this->assertTrue($media->equals($other));
    }

    public function testEqualsReturnsFalseWhenNeitherIdNorNameMatch(): void
    {
        $media = (new VichMediaTraitStub())->setName('logo.webp');
        $other = (new VichMediaTraitStub())->setName('banner.webp');

        $this->assertFalse($media->equals($other));
    }

    public function testEqualsReturnsFalseWhenNamesAreBothEmpty(): void
    {
        $media = new VichMediaTraitStub();
        $other = new VichMediaTraitStub();

        $this->assertFalse($media->equals($other));
    }

    public function testSetPositionDefaultsNullToZero(): void
    {
        $media = (new VichMediaTraitStub())->setPosition(null);

        $this->assertSame(0, $media->getPosition());
    }

    public function testSetFileStampsUpdatedAt(): void
    {
        $media = new VichMediaTraitStub();

        $media->setFile(new File(__FILE__));

        $this->assertNotNull($media->getUpdatedAt());
    }

    public function testSetFileWithNullDoesNotStampUpdatedAt(): void
    {
        $media = new VichMediaTraitStub();

        $media->setFile(null);

        $this->assertNull($media->getUpdatedAt());
    }

    public function testGetSetUser(): void
    {
        $media = new VichMediaTraitStub();
        $user = $this->createStub(UserInterface::class);

        $media->setUser($user);

        $this->assertSame($user, $media->getUser());
    }
}
