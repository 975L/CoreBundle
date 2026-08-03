<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\BlockMediaUsageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockMediaUsageProviderTest extends TestCase
{
    // Media::$id has no public setter (assigned by Doctrine on persist) - reflection mirrors that for a real, non-null array key, avoiding the "null used as array offset" deprecation
    private function assignId(Media $media, int $id): void
    {
        (new \ReflectionProperty(Media::class, 'id'))->setValue($media, $id);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $key, array $params) => $key . ' ' . implode(',', $params)
        );

        return $translator;
    }

    // Medias not attached to any Block (site-wide graphics: favicon, og-image...) contribute no usage
    public function testGetUsagesSkipsMediaNotAttachedToABlock(): void
    {
        $media = new Media();
        $provider = new BlockMediaUsageProvider($this->createTranslator());

        $this->assertSame([], $provider->getUsages([$media]));
    }

    public function testGetUsagesDescribesMediaAttachedToABlockByLabel(): void
    {
        $block = new Block();
        $block->setLabel('Article');

        $media = new Media();
        $media->setBlock($block);
        $this->assignId($media, 1);

        $provider = new BlockMediaUsageProvider($this->createTranslator());
        $usages = $provider->getUsages([$media]);

        $this->assertArrayHasKey(1, $usages);
        $this->assertStringContainsString('Article', $usages[1][0]['label']);
        $this->assertNull($usages[1][0]['url']);
    }

    // Falls back to the raw kind when the Block has no resolved label (e.g. BlockLabelListener hasn't run)
    public function testGetUsagesFallsBackToKindWhenBlockHasNoLabel(): void
    {
        $block = new Block();
        $block->setKind('article');

        $media = new Media();
        $media->setBlock($block);
        $this->assignId($media, 2);

        $provider = new BlockMediaUsageProvider($this->createTranslator());
        $usages = $provider->getUsages([$media]);

        $this->assertStringContainsString('article', $usages[2][0]['label']);
    }

    public function testGetUsagesHandlesMultipleMedias(): void
    {
        $block = new Block();
        $block->setLabel('Card');

        $attached = new Media();
        $attached->setBlock($block);
        $this->assignId($attached, 3);
        $unattached = new Media();
        $this->assignId($unattached, 4);

        $provider = new BlockMediaUsageProvider($this->createTranslator());
        $usages = $provider->getUsages([$attached, $unattached]);

        $this->assertCount(1, $usages);
    }
}
