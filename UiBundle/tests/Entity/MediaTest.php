<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    public function testIsOgImageIsTrueForTheSiteWideOgImageRole(): void
    {
        $media = new Media()->setRole(Media::ROLE_OG_IMAGE);

        $this->assertTrue($media->isOgImage());
        $this->assertSame(600, $media->getImageWidth());
    }

    // Regression guard: role=null/block=null used to also mean "og-image" (a Page's own og-image override), a heuristic that broke once MediaCrudController's New action could produce that exact same state for a plain library Media - isOgImage() must no longer treat it as an og-image
    public function testIsOgImageIsFalseWithNoRoleAndNoBlock(): void
    {
        $media = new Media();

        $this->assertFalse($media->isOgImage());
        $this->assertSame(800, $media->getImageWidth());
    }

    public function testIsOgImageIsFalseForABlockAttachedMedia(): void
    {
        $media = new Media()->setBlock(new Block());

        $this->assertFalse($media->isOgImage());
        $this->assertSame(800, $media->getImageWidth());
    }

    public function testGetImageWidthUsesMaxWidthsForARoleDeclaredThere(): void
    {
        $media = new Media()->setRole(Media::ROLE_LOGO);

        $this->assertSame(600, $media->getImageWidth());
    }

    // Hero crops tightly via CSS object-fit:cover (see sass/_page-sections.scss) - needs a wider stored image than other block kinds to avoid pixelating on retina displays
    public function testGetImageWidthUsesBlockKindMaxWidthsForHero(): void
    {
        $media = new Media()->setBlock(new Block()->setKind('hero'));

        $this->assertSame(1200, $media->getImageWidth());
    }

    public function testGetIntrinsicDimensionsReturnTheValueWhenItIsABarePixelCount(): void
    {
        $media = new Media()->setWidth('600')->setHeight('120');

        $this->assertSame(600, $media->getIntrinsicWidth());
        $this->assertSame(120, $media->getIntrinsicHeight());
    }

    // The admin fields are free text, and a css length in an HTML width/height attribute is silently discarded by browsers - see getIntrinsicWidth()
    public function testGetIntrinsicDimensionsReturnNullForACssLength(): void
    {
        $media = new Media()->setWidth('50%')->setHeight('100px');

        $this->assertNull($media->getIntrinsicWidth());
        $this->assertNull($media->getIntrinsicHeight());
    }

    public function testGetIntrinsicDimensionsReturnNullWhenUnset(): void
    {
        $media = new Media();

        $this->assertNull($media->getIntrinsicWidth());
        $this->assertNull($media->getIntrinsicHeight());
    }
}
