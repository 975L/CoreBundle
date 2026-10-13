<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Twig\PwaExtension;
use PHPUnit\Framework\TestCase;

class PwaExtensionTest extends TestCase
{
    // The listing's address is the package's, the one the Digital Asset Links already name
    public function testPlayStoreUrlIsDerivedFromThePackage(): void
    {
        $this->assertSame(
            'https://play.google.com/store/apps/details?id=com.c975l.papacalin',
            $this->extension(' com.c975l.papacalin ')->playStoreUrl()
        );
    }

    public function testPlayStoreUrlIsNullWithoutPackage(): void
    {
        $this->assertNull($this->extension('')->playStoreUrl());
        $this->assertNull($this->extension(null)->playStoreUrl());
    }

    // A screen whose file never made it to disk has nothing to show
    public function testScreenshotsLeaveOutTheOnesWithoutFile(): void
    {
        $kept = new Media()->setFilename('medias/screen-1.webp');
        $screenshots = $this->extension(null, [new Media(), $kept])->screenshots();

        $this->assertSame([$kept], $screenshots);
    }

    /** @param list<Media> $medias */
    private function extension(?string $package, array $medias = []): PwaExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($package);

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findBy')->willReturn($medias);

        return new PwaExtension($configService, $mediaRepository);
    }
}
