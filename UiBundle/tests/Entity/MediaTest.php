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

    // The dark logo is the same drawing on another ground: one singleton row like the light one, and resized to the same width, a pair coming out at two sizes showing the navbar jump on a theme switch
    public function testTheDarkLogoIsASingletonRoleResizedLikeTheLightOne(): void
    {
        $media = new Media()->setRole(Media::ROLE_LOGO_ON_DARK);

        $this->assertSame(600, $media->getImageWidth());
        $this->assertContains(Media::ROLE_LOGO_ON_DARK, Media::getSingletonRoles());
    }

    // Hero crops tightly via CSS object-fit:cover (see sass/_page-sections.scss) - needs a wider stored image than other block kinds to avoid pixelating on retina displays
    public function testGetImageWidthUsesBlockKindMaxWidthsForHero(): void
    {
        $media = new Media()->setBlock(new Block()->setKind('hero'));

        $this->assertSame(1200, $media->getImageWidth());
    }

    // The overlay a render lays on: the three getters answer the language being rendered, the row itself untouched
    public function testTheThreeTextsAnswerWhatTheLanguageSaysOnceItIsLaidOn(): void
    {
        $media = new Media()
            ->setLabel('La boutique de démonstration')
            ->setDescription('Un catalogue et ses filtres.')
            ->setAlt('Capture de la boutique');
        $media->setTranslated(['label' => 'The demonstration shop', 'alt' => 'A screenshot of the shop']);

        $this->assertSame('The demonstration shop', $media->getLabel());
        $this->assertSame('A screenshot of the shop', $media->getAlt());
        $this->assertSame('Un catalogue et ses filtres.', $media->getDescription(), 'A field nobody translated keeps the text it was written in.');
    }

    // What Doctrine writes back and what a form screen shows: the mapped property, never the overlay above it
    public function testTheRowGoesOnHoldingTheWordsItWasWrittenIn(): void
    {
        $media = new Media()->setLabel('La boutique de démonstration');
        $media->setTranslated(['label' => 'The demonstration shop']);

        $this->assertSame('La boutique de démonstration', $media->getUntranslated('label'));
        $this->assertSame('La boutique de démonstration', new \ReflectionProperty(Media::class, 'label')->getValue($media));
    }

    // Nothing laid on at all - a single-language site, or a form screen - and the getters answer the row itself
    public function testTheGettersAnswerTheRowWhenNoLanguageWasLaidOn(): void
    {
        $media = new Media()->setLabel('La boutique de démonstration');

        $this->assertSame('La boutique de démonstration', $media->getLabel());
        $this->assertNull($media->getUntranslated('credits'), 'Only the three texts are addressable - the file, its link and its credits are the same in every language.');
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
