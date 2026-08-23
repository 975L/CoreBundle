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
use c975L\ConfigBundle\Twig\CreditsExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;
use Twig\TwigFunction;

class CreditsExtensionTest extends TestCase
{
    // A stub reading like the real service: get() returns the stored value, getBool() casts it the way ConfigService does
    private function createExtension(mixed $storedValue): CreditsExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($storedValue);
        $configService->method('getBool')->willReturnCallback(
            static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        );

        return new CreditsExtension($configService);
    }

    public function testGetFunctionsExposesTheCreditsTwigFunctions(): void
    {
        $functions = new AttributeExtension(CreditsExtension::class)->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);
        $this->assertSame(['credits_mode', 'made_by_label'], array_map(static fn (TwigFunction $function): string => $function->getName(), $functions));
    }

    public function testEveryDeclaredModeIsReturnedAsItIs(): void
    {
        foreach (CreditsExtension::MODES as $mode) {
            $this->assertSame($mode, $this->createExtension($mode)->getCreditsMode('display-made-by'));
        }
    }

    // The four modes must match what config/configs.json offers, the select being built from that list alone
    public function testModesMatchTheDeclaredChoices(): void
    {
        $configs = json_decode((string) file_get_contents(__DIR__ . '/../../config/configs.json'), true, 512, \JSON_THROW_ON_ERROR);

        foreach ($configs as $config) {
            if (in_array($config['slug'], ['display-made-by', 'display-hosted-by'], true)) {
                $this->assertSame(CreditsExtension::MODES, $config['choices'], sprintf('"%s" offers other modes than the extension knows', $config['slug']));
            }

            if ('made-by-wording' === $config['slug']) {
                $this->assertSame(CreditsExtension::WORDINGS, $config['choices'], '"made-by-wording" offers other wordings than the extension knows');
            }
        }
    }

    // A site upgraded from the bool era: the row still holds true/false and is read as a real bool until c975l:config:load-all has run
    public function testALegacyBooleanValueReadsAsLogoOrNone(): void
    {
        $this->assertSame(CreditsExtension::MODE_LOGO, $this->createExtension(true)->getCreditsMode('display-made-by'));
        $this->assertSame(CreditsExtension::MODE_NONE, $this->createExtension(false)->getCreditsMode('display-made-by'));
    }

    // Same site, once the kind has become a choice: the very same value comes back as the raw string, "false" included - which is truthy in Twig, so a credit switched off must not come back on
    public function testALegacyStringValueReadsAsLogoOrNone(): void
    {
        $this->assertSame(CreditsExtension::MODE_LOGO, $this->createExtension('true')->getCreditsMode('display-hosted-by'));
        $this->assertSame(CreditsExtension::MODE_NONE, $this->createExtension('false')->getCreditsMode('display-hosted-by'));
    }

    // Only the explicit "powered" wording credits the system rather than the maker, everything else - unset row included - keeping the historical "Made by"
    public function testOnlyThePoweredWordingChangesTheLabel(): void
    {
        $this->assertSame('label.powered_by', $this->createExtension(CreditsExtension::WORDING_POWERED)->getMadeByLabel());
        $this->assertSame('label.made_by', $this->createExtension(CreditsExtension::WORDING_MADE)->getMadeByLabel());
        $this->assertSame('label.made_by', $this->createExtension(null)->getMadeByLabel());
        $this->assertSame('label.made_by', $this->createExtension('whatever')->getMadeByLabel());
    }

    // An empty row (or a slug nothing declares) shows nothing rather than falling back on a credit the site never asked for
    public function testAnUnsetValueReadsAsNone(): void
    {
        $this->assertSame(CreditsExtension::MODE_NONE, $this->createExtension(null)->getCreditsMode('display-made-by'));
        $this->assertSame(CreditsExtension::MODE_NONE, $this->createExtension('')->getCreditsMode('display-made-by'));
        $this->assertSame(CreditsExtension::MODE_NONE, $this->createExtension('whatever')->getCreditsMode('display-made-by'));
    }
}
