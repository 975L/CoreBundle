<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Management\GuidedProjectKeyGenerator;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use c975L\UiBundle\Management\DonovanWidgetProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class DonovanWidgetProviderTest extends TestCase
{
    private function createAssistantClient(bool $enabled): AiAssistantClientInterface
    {
        $client = $this->createStub(AiAssistantClientInterface::class);
        $client->method('isEnabled')->willReturn($enabled);

        return $client;
    }

    private function createSecurity(bool $isSuperAdmin): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isSuperAdmin);

        return $security;
    }

    private function createProvider(bool $enabled, bool $isSuperAdmin): DonovanWidgetProvider
    {
        $keyGenerator = $this->createStub(GuidedProjectKeyGenerator::class);
        $keyGenerator->method('getKey')->willReturn('0123456789abcdef');

        return new DonovanWidgetProvider($this->createAssistantClient($enabled), $this->createSecurity($isSuperAdmin), $keyGenerator);
    }

    public function testReturnsNoWidgetWhenNotEnabled(): void
    {
        $provider = $this->createProvider(false, true);

        $this->assertSame([], $provider->getPageOverlays());
    }

    public function testReturnsNoWidgetWhenEnabledButNotSuperAdmin(): void
    {
        $provider = $this->createProvider(true, false);

        $this->assertSame([], $provider->getPageOverlays());
    }

    public function testReturnsThePanelWhenEnabledAndSuperAdmin(): void
    {
        $provider = $this->createProvider(true, true);

        $widgets = $provider->getPageOverlays();

        $this->assertCount(1, $widgets);
        $this->assertSame('@c975LUi/management/_donovan_panel.html.twig', $widgets[0]['template']);
        $this->assertSame(['key' => '0123456789abcdef'], $widgets[0]['context']);
    }
}
