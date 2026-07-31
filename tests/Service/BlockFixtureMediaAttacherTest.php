<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use PHPUnit\Framework\TestCase;

class BlockFixtureMediaAttacherTest extends TestCase
{
    // The five images mirror what a real showcase site declares (see PlaceholderMediaProviderInterface) - enough for the rotation below to actually rotate
    private const IMAGES = [
        'showcase/photo-1.webp',
        'showcase/photo-2.webp',
        'showcase/photo-3.webp',
        'showcase/photo-4.webp',
        'showcase/photo-5.webp',
    ];
    private const VIDEO = 'showcase/clip.mp4';
    private const AUDIO = 'showcase/loop.mp3';
    private const DOCUMENT = 'showcase/brochure.pdf';

    private function createRegistry(array $mediaTypes, bool $multiUpload = false): BlockRegistry
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getMediaTypes')->willReturn($mediaTypes);
        $registry->method('allowsMultiUpload')->willReturn($multiUpload);

        return $registry;
    }

    private function createPlaceholderMedia(?array $media = null): PlaceholderMediaRegistry
    {
        $provider = $this->createStub(PlaceholderMediaProviderInterface::class);
        $provider->method('getPlaceholderMedia')->willReturn($media ?? [
            'images' => self::IMAGES,
            'video' => self::VIDEO,
            'audio' => self::AUDIO,
            'document' => self::DOCUMENT,
        ]);

        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($provider);

        return $registry;
    }

    private function createAttacher(array $mediaTypes, bool $multiUpload = false, ?array $media = null): BlockFixtureMediaAttacher
    {
        return new BlockFixtureMediaAttacher(
            $this->createRegistry($mediaTypes, $multiUpload),
            $this->createPlaceholderMedia($media),
        );
    }

    // A single image/* kind (e.g. "image", "article", "hero"...) only ever reads its first media - one placeholder is enough, drawn from the rotating pool
    public function testSingleImageKindGetsOnePlaceholderImage(): void
    {
        $attacher = $this->createAttacher(['image/*']);
        $block = (new Block())->setKind('image');

        $attacher->attach($block, 'image');

        $this->assertCount(1, $block->getMedia());
        $this->assertContains($block->getMedia()->first()->getFilename(), self::IMAGES);
    }

    // image_compare needs two distinct images to look like a real before/after comparison, not two copies of the same one
    public function testImageCompareGetsTwoDistinctPlaceholderImages(): void
    {
        $attacher = $this->createAttacher(['image/*']);
        $block = (new Block())->setKind('image_compare');

        $attacher->attach($block, 'image_compare');

        $medias = $block->getMedia();
        $this->assertCount(2, $medias);
        $this->assertNotSame($medias->first()->getFilename(), $medias->last()->getFilename());
    }

    // slider mixes 2 images with 1 video slide, to showcase its mixed-media support - it's tagged media_multi_upload (see services.yaml), the generic signal for "several images"
    public function testSliderGetsTwoImagesAndOneVideo(): void
    {
        $attacher = $this->createAttacher(['image/*', 'video/*'], multiUpload: true);
        $block = (new Block())->setKind('slider');

        $attacher->attach($block, 'slider');

        $medias = $block->getMedia();
        $this->assertCount(3, $medias);
        $this->assertSame(self::VIDEO, $medias->last()->getFilename());
    }

    // The "freeflow" variant needs enough slides to actually demonstrate its distinct scrolling layout - 5 images, no video mixed in (unlike the default variant, already covered above)
    public function testSliderFreeflowVariantGetsFiveImagesAndNoVideo(): void
    {
        $attacher = $this->createAttacher(['image/*', 'video/*'], multiUpload: true);
        $block = (new Block())->setKind('slider');

        $attacher->attach($block, 'slider', 'freeflow');

        $medias = $block->getMedia();
        $this->assertCount(5, $medias);
        foreach ($medias as $media) {
            $this->assertContains($media->getFilename(), self::IMAGES);
        }
    }

    // Regression guard: video mixing used to be hardcoded to "slider" by name - any kind whose own media_types include video/* gets a video slide now, without UiBundle needing to know its name
    public function testAnyKindWithVideoMediaTypeGetsAVideoMixedIn(): void
    {
        $attacher = $this->createAttacher(['image/*', 'video/*'], multiUpload: true);
        $block = (new Block())->setKind('gallery_carousel');

        $attacher->attach($block, 'gallery_carousel');

        $this->assertSame(self::VIDEO, $block->getMedia()->last()->getFilename());
    }

    // "video" lists its accepted formats one by one ("video/mp4,video/webm,video/ogg", see services.yaml) - that's still a single video upload, plus one image standing in for the player's cover
    public function testVideoKindGetsOneVideoAndOneCoverImage(): void
    {
        $attacher = $this->createAttacher(['video/mp4', 'video/webm', 'video/ogg', 'image/*']);
        $block = (new Block())->setKind('video');

        $attacher->attach($block, 'video');

        $medias = $block->getMedia();
        $this->assertCount(2, $medias);
        $this->assertSame(self::VIDEO, $medias->first()->getFilename());
        $this->assertContains($medias->last()->getFilename(), self::IMAGES);
    }

    // blocks/Video.html.twig tells the cover image apart from the video by mimetype - a placeholder image with none would never be picked up as a cover
    public function testPlaceholderImagesCarryAMimeType(): void
    {
        $attacher = $this->createAttacher(['image/*']);

        $this->assertSame('image/webp', $attacher->nextPlaceholderImage()->getMimeType());
    }

    // A wrong mimetype would have templates sorting a block's medias into the wrong slot, so it follows the declared file's own extension - an app is free to serve a .jpg or a .webm
    public function testEachPlaceholderCarriesTheMimeTypeOfItsOwnExtension(): void
    {
        $attacher = $this->createAttacher(
            ['video/*', 'image/*'],
            media: ['images' => ['showcase/photo-1.jpg'], 'video' => 'showcase/clip.webm'],
        );
        $block = (new Block())->setKind('video');

        $attacher->attach($block, 'video');

        $this->assertSame('video/webm', $block->getMedia()->first()->getMimeType());
        $this->assertSame('image/jpeg', $block->getMedia()->last()->getMimeType());
    }

    // .ogg names an audio file as readily as a video one, so the extension alone can't tell - the slot the file was declared for does, and tagging a video "audio/ogg" would have blocks/Video.html.twig render no player at all
    public function testAnAmbiguousExtensionIsReadWithinItsOwnSlotFamily(): void
    {
        $attacher = $this->createAttacher(['video/*'], media: ['video' => 'showcase/clip.ogg']);
        $block = (new Block())->setKind('video');

        $attacher->attach($block, 'video');

        $this->assertSame('video/ogg', $block->getMedia()->first()->getMimeType());

        $attacher = $this->createAttacher(['audio/*'], media: ['audio' => 'showcase/theme.ogg']);
        $block = (new Block())->setKind('audio');

        $attacher->attach($block, 'audio');

        $this->assertSame('audio/ogg', $block->getMedia()->first()->getMimeType());
    }

    // article is tagged media_multi_upload too, but wants 3 images specifically (Laurent's call), more than the generic multi-upload default of 2
    public function testArticleGetsThreeImages(): void
    {
        $attacher = $this->createAttacher(['image/*'], multiUpload: true);
        $block = (new Block())->setKind('article');

        $attacher->attach($block, 'article');

        $this->assertCount(3, $block->getMedia());
    }

    // Regression guard: the "several images" count used to be hardcoded to "slider"/"image_compare" by name - any kind tagged media_multi_upload gets 2 now, without UiBundle needing to know its name
    public function testAnyMultiUploadKindGetsTwoImagesByDefault(): void
    {
        $attacher = $this->createAttacher(['image/*'], multiUpload: true);
        $block = (new Block())->setKind('gallery_carousel');

        $attacher->attach($block, 'gallery_carousel');

        $this->assertCount(2, $block->getMedia());
    }

    // A kind with no media_multi_upload tag only ever gets 1 image, even if the registry stub happens to return other media types alongside it
    public function testNonMultiUploadKindGetsOneImage(): void
    {
        $attacher = $this->createAttacher(['image/*'], multiUpload: false);
        $block = (new Block())->setKind('hero');

        $attacher->attach($block, 'hero');

        $this->assertCount(1, $block->getMedia());
    }

    // Rotation is shared across calls (not reset per attach()) - consecutive blocks built in the same request/page don't all show the same photo. reset() restarts it, e.g. at the top of a new request.
    public function testImagesRotateAcrossSuccessiveCallsUntilReset(): void
    {
        $attacher = $this->createAttacher(['image/*']);

        $first = (new Block())->setKind('image');
        $attacher->attach($first, 'image');
        $second = (new Block())->setKind('image');
        $attacher->attach($second, 'image');

        $this->assertNotSame($first->getMedia()->first()->getFilename(), $second->getMedia()->first()->getFilename());

        $attacher->reset();
        $third = (new Block())->setKind('image');
        $attacher->attach($third, 'image');

        $this->assertSame($first->getMedia()->first()->getFilename(), $third->getMedia()->first()->getFilename());
    }

    // audio/* gets the single declared audio clip attached
    public function testAudioKindGetsThePlaceholderAudioAttached(): void
    {
        $attacher = $this->createAttacher(['audio/*']);
        $block = (new Block())->setKind('audio');

        $attacher->attach($block, 'audio');

        $this->assertSame(self::AUDIO, $block->getMedia()->first()->getFilename());
    }

    // application/pdf (e.g. "document_download") gets the single declared PDF attached
    public function testPdfKindGetsThePlaceholderDocumentAttached(): void
    {
        $attacher = $this->createAttacher(['application/pdf']);
        $block = (new Block())->setKind('document_download');

        $attacher->attach($block, 'document_download');

        $this->assertSame(self::DOCUMENT, $block->getMedia()->first()->getFilename());
    }

    // portfolio_grid bypasses the generic per-mediaType mechanism entirely: it gets several distinctly-captioned project cards instead of N copies of the same placeholder image
    public function testPortfolioGridGetsSeveralDistinctlyCaptionedProjects(): void
    {
        $attacher = $this->createAttacher(['image/*']);
        $block = (new Block())->setKind('portfolio_grid');

        $attacher->attach($block, 'portfolio_grid');

        $medias = $block->getMedia();
        $this->assertCount(3, $medias, 'portfolio_grid should get several distinct project cards, not a single placeholder');
        $this->assertSame('Refonte e-commerce', $medias->first()->getLabel());
        $this->assertNotNull($medias->first()->getDescription());
        $this->assertNotNull($medias->first()->getUrl());
    }

    // A kind with no media_types at all is left untouched, not crashed
    public function testKindWithNoMediaTypesGetsNothingAttached(): void
    {
        $attacher = $this->createAttacher([]);
        $block = (new Block())->setKind('alert');

        $attacher->attach($block, 'alert');

        $this->assertCount(0, $block->getMedia());
    }

    // The bundle ships no placeholder file of its own: an app registering no provider gets a block with no media rather than one pointing at files that aren't there
    public function testNothingIsAttachedWhenTheAppDeclaresNoPlaceholderMedia(): void
    {
        $attacher = new BlockFixtureMediaAttacher($this->createRegistry(['image/*', 'video/*'], multiUpload: true));
        $block = (new Block())->setKind('slider');

        $attacher->attach($block, 'slider');

        $this->assertCount(0, $block->getMedia());
        $this->assertNull($attacher->nextPlaceholderImage());
    }

    // Same with a registry that exists but carries nothing - no partially-built Media with an empty filename
    public function testNothingIsAttachedWhenTheRegistryIsEmpty(): void
    {
        $attacher = $this->createAttacher(['audio/*'], media: []);
        $block = (new Block())->setKind('audio');

        $attacher->attach($block, 'audio');

        $this->assertCount(0, $block->getMedia());
    }

    // Declaring only part of the media is legitimate (see the interface) - the kinds it covers still get theirs, the others simply get none
    public function testPartiallyDeclaredPlaceholderMediaOnlyCoversWhatItDeclares(): void
    {
        $attacher = $this->createAttacher(['audio/*'], media: ['images' => self::IMAGES]);
        $block = (new Block())->setKind('audio');

        $attacher->attach($block, 'audio');

        $this->assertCount(0, $block->getMedia());
    }

    // portfolio_grid builds its cards off the image pool - with none declared it yields no card at all, rather than three broken ones
    public function testPortfolioGridGetsNoProjectWhenNoImageIsDeclared(): void
    {
        $attacher = $this->createAttacher(['image/*'], media: []);
        $block = (new Block())->setKind('portfolio_grid');

        $attacher->attach($block, 'portfolio_grid');

        $this->assertCount(0, $block->getMedia());
    }
}
