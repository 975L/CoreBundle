<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Twig;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Twig\LocalizedPathExtension;
use PHPUnit\Framework\TestCase;

class LocalizedPathExtensionTest extends TestCase
{
    // The whole rule lives in the generator, the extension being what a template reaches it through
    public function testLocalizedPathHandsTheRouteItsParametersAndItsLanguagesOver(): void
    {
        $generator = $this->createMock(LocalizedUrlGenerator::class);
        $generator->expects($this->once())
            ->method('path')
            ->with('shop_index', ['page' => 2], ['en'])
            ->willReturn('/en/shop?page=2');

        $this->assertSame('/en/shop?page=2', new LocalizedPathExtension($generator)->localizedPath('shop_index', ['page' => 2], ['en']));
    }

    // A route saying the same thing in every language names none: the twin is taken wherever it exists
    public function testLocalizedPathAsksForEveryLanguageWhenNoneIsNamed(): void
    {
        $generator = $this->createMock(LocalizedUrlGenerator::class);
        $generator->expects($this->once())
            ->method('path')
            ->with('shop_index', [], null)
            ->willReturn('/shop');

        $this->assertSame('/shop', new LocalizedPathExtension($generator)->localizedPath('shop_index'));
    }

    public function testScreenLanguagesGivesBackWhatTheGeneratorOffers(): void
    {
        $generator = $this->createStub(LocalizedUrlGenerator::class);
        $generator->method('screenLanguages')->willReturn(['en' => '/shop?_locale=en', 'es' => '/shop?_locale=es']);

        $this->assertSame(['en' => '/shop?_locale=en', 'es' => '/shop?_locale=es'], new LocalizedPathExtension($generator)->screenLanguages());
    }
}
