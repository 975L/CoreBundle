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
use c975L\UiBundle\Twig\MatomoExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Attribute\AsTwigFunction;

// The tracker snippet and the bundles pushing a measure of their own all ask this one function, so a drift here either tracks a site that turned Matomo off or silently stops every measure
class MatomoExtensionTest extends TestCase
{
    // The name is written in an attribute and nowhere else, so it is what the templates calling it rely on
    public function testTheFunctionIsRegisteredUnderTheNameTheTemplatesCall(): void
    {
        $attributes = new \ReflectionMethod(MatomoExtension::class, 'isEnabled')->getAttributes(AsTwigFunction::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('matomo_enabled', $attributes[0]->getArguments()[0]);
    }

    // Enabled only with the switch on and both values a tracker url is built from filled
    #[DataProvider('provideConfigs')]
    public function testItNeedsTheSwitchAndBothValues(mixed $enabled, ?string $url, ?string $id, bool $expected): void
    {
        $values = [
            'site-enable-matomo' => $enabled,
            'site-matomo-url' => $url,
            'site-matomo-id' => $id,
        ];

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $values[$key] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn ($value): bool => filter_var($value, \FILTER_VALIDATE_BOOLEAN));

        $this->assertSame($expected, new MatomoExtension($configService)->isEnabled());
    }

    public static function provideConfigs(): iterable
    {
        yield 'everything filled' => [true, 'https://stats.example.com', '3', true];
        yield 'switch stored as a string' => ['true', 'https://stats.example.com', '3', true];
        yield 'switch off' => [false, 'https://stats.example.com', '3', false];
        yield 'no url' => [true, null, '3', false];
        yield 'blank url' => [true, '  ', '3', false];
        yield 'no id' => [true, 'https://stats.example.com', null, false];
    }
}
