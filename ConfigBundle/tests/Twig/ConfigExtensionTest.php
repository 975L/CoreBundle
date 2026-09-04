<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\ConfigTranslator;
use c975L\ConfigBundle\Twig\ConfigExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;
use Twig\TwigFunction;

class ConfigExtensionTest extends TestCase
{
    public function testGetFunctionsExposesAConfigTwigFunction(): void
    {
        $functions = new AttributeExtension(ConfigExtension::class)->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertSame('config', $functions[0]->getName());
    }

    public function testGetConfigDelegatesToConfigService(): void
    {
        $extension = new ConfigExtension($this->createConfigService(), $this->createTranslator());

        $this->assertSame('My Site', $extension->getConfig('site-name'));
        $this->assertNull($extension->getConfig('unknown-slug'));
    }

    // A site declaring one language, which is every c975L site until it says otherwise: the layer hands back what it was given, untouched and whatever its kind
    public function testTheValueIsHandedBackUntouchedWhenNothingIsTranslated(): void
    {
        $extension = new ConfigExtension($this->createConfigService(), $this->createTranslator());

        $this->assertTrue($extension->getConfig('site-maintenance'));
        $this->assertSame(12, $extension->getConfig('site-items-per-page'));
    }

    // What a book sheet asks for, reading in the book's own language rather than in the visitor's
    public function testTheAskedLanguageReachesTheTranslator(): void
    {
        $translator = $this->createMock(ConfigTranslator::class);
        $translator->expects($this->once())
            ->method('value')
            ->with('site-name', 'My Site', 'en')
            ->willReturn('My English Site');

        $extension = new ConfigExtension($this->createConfigService(), $translator);

        $this->assertSame('My English Site', $extension->getConfig('site-name', 'en'));
    }

    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $slug) => match ($slug) {
                'site-name' => 'My Site',
                'site-maintenance' => true,
                'site-items-per-page' => 12,
                default => null,
            },
        );

        return $configService;
    }

    // The layer as a single-language site has it: every value handed straight back (see ConfigTranslator::value())
    private function createTranslator(): ConfigTranslator
    {
        $translator = $this->createStub(ConfigTranslator::class);
        $translator->method('value')->willReturnCallback(static fn (string $slug, mixed $value): mixed => $value);

        return $translator;
    }
}
