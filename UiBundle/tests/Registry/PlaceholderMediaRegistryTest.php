<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use PHPUnit\Framework\TestCase;

class PlaceholderMediaRegistryTest extends TestCase
{
    private function createProvider(array $media): PlaceholderMediaProviderInterface
    {
        $provider = $this->createStub(PlaceholderMediaProviderInterface::class);
        $provider->method('getPlaceholderMedia')->willReturn($media);

        return $provider;
    }

    // Nothing declared: every getter stays empty, BlockFixtureMediaAttacher then attaching no media at all - the bundle ships no placeholder file of its own to fall back on
    public function testAnEmptyRegistryDeclaresNothing(): void
    {
        $registry = new PlaceholderMediaRegistry();

        $this->assertSame([], $registry->getImages());
        $this->assertNull($registry->getVideo());
        $this->assertNull($registry->getVideoEmbed());
        $this->assertNull($registry->getAudio());
        $this->assertNull($registry->getDocument());
    }

    public function testEachDeclaredMediaIsExposedByItsOwnGetter(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider([
            'images' => ['medias/demo/photo-1.webp', 'medias/demo/photo-2.webp'],
            'video' => 'medias/demo/clip.mp4',
            'video_embed' => 'medias/demo/clip-embed.html',
            'audio' => 'medias/demo/loop.mp3',
            'document' => 'medias/demo/brochure.pdf',
        ]));

        $this->assertSame(['medias/demo/photo-1.webp', 'medias/demo/photo-2.webp'], $registry->getImages());
        $this->assertSame('medias/demo/clip.mp4', $registry->getVideo());
        $this->assertSame('medias/demo/clip-embed.html', $registry->getVideoEmbed());
        $this->assertSame('medias/demo/loop.mp3', $registry->getAudio());
        $this->assertSame('medias/demo/brochure.pdf', $registry->getDocument());
    }

    // Declaring only part of the media is legitimate (see the interface), so a provider merges into what's already there rather than replacing it wholesale
    public function testProvidersMergeKeyByKey(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['images' => ['medias/demo/photo-1.webp'], 'video' => 'medias/demo/clip.mp4']));
        $registry->addProvider($this->createProvider(['audio' => 'medias/demo/loop.mp3']));

        $this->assertSame(['medias/demo/photo-1.webp'], $registry->getImages());
        $this->assertSame('medias/demo/clip.mp4', $registry->getVideo());
        $this->assertSame('medias/demo/loop.mp3', $registry->getAudio());
    }

    // An empty value is a key the provider simply doesn't cover, not an instruction to blank out what another one declared
    public function testAnEmptyValueNeverOverridesAnAlreadyDeclaredOne(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['images' => ['medias/demo/photo-1.webp'], 'video' => 'medias/demo/clip.mp4']));
        $registry->addProvider($this->createProvider(['images' => [], 'video' => '']));

        $this->assertSame(['medias/demo/photo-1.webp'], $registry->getImages());
        $this->assertSame('medias/demo/clip.mp4', $registry->getVideo());
    }

    // Two providers genuinely covering the same key: the last one registered wins, same as every other c975L registry
    public function testALaterProviderOverridesAnEarlierOne(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['video' => 'medias/demo/clip.mp4']));
        $registry->addProvider($this->createProvider(['video' => 'medias/demo/other.webm']));

        $this->assertSame('medias/demo/other.webm', $registry->getVideo());
    }

    // The pictures of one named row, which no rotation through the generic pool can stand in for
    public function testTheImagesOfOneNamedThingAreReadBackInOrder(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['keyed_images' => [
            'shop/table-basse-chene' => ['showcase/shop/table-1.webp', 'showcase/shop/table-2.webp'],
        ]]));

        $this->assertSame(['showcase/shop/table-1.webp', 'showcase/shop/table-2.webp'], $registry->getImagesFor('shop/table-basse-chene'));
        $this->assertSame([], $registry->getImagesFor('shop/chaise-bistrot'));
    }

    // Merged one named thing at a time: a provider declaring the pictures of a single product would otherwise take away every other provider's
    public function testAProviderDeclaringOneKeyLeavesTheOthersAlone(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['keyed_images' => ['shop/table-basse-chene' => ['showcase/shop/table-1.webp']]]));
        $registry->addProvider($this->createProvider(['keyed_images' => ['book/le-fil-rouge-1' => ['showcase/book/cover-1.webp']]]));

        $this->assertSame(['showcase/shop/table-1.webp'], $registry->getImagesFor('shop/table-basse-chene'));
        $this->assertSame(['showcase/book/cover-1.webp'], $registry->getImagesFor('book/le-fil-rouge-1'));
    }

    // A named thing left empty is a row the provider simply doesn't carry, not an instruction to blank out the pictures another one declared
    public function testAnEmptyNamedThingNeverOverridesAnAlreadyDeclaredOne(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['keyed_images' => ['shop/table-basse-chene' => ['showcase/shop/table-1.webp']]]));
        $registry->addProvider($this->createProvider(['keyed_images' => ['shop/table-basse-chene' => []]]));

        $this->assertSame(['showcase/shop/table-1.webp'], $registry->getImagesFor('shop/table-basse-chene'));
    }

    // Two providers naming the same row: the last one registered wins, as everywhere else here
    public function testALaterProviderOverridesTheImagesOfTheSameThing(): void
    {
        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($this->createProvider(['keyed_images' => ['shop/table-basse-chene' => ['showcase/shop/table-1.webp']]]));
        $registry->addProvider($this->createProvider(['keyed_images' => ['shop/table-basse-chene' => ['showcase/shop/other.webp']]]));

        $this->assertSame(['showcase/shop/other.webp'], $registry->getImagesFor('shop/table-basse-chene'));
    }
}
