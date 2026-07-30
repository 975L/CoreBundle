<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

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

    public function testReturnsNoWidgetWhenNotEnabled(): void
    {
        $provider = new DonovanWidgetProvider($this->createAssistantClient(false), $this->createSecurity(true));

        $this->assertSame([], $provider->getDashboardWidgets());
    }

    public function testReturnsNoWidgetWhenEnabledButNotSuperAdmin(): void
    {
        $provider = new DonovanWidgetProvider($this->createAssistantClient(true), $this->createSecurity(false));

        $this->assertSame([], $provider->getDashboardWidgets());
    }

    public function testReturnsTheDashboardCardWhenEnabledAndSuperAdmin(): void
    {
        $provider = new DonovanWidgetProvider($this->createAssistantClient(true), $this->createSecurity(true));

        $widgets = $provider->getDashboardWidgets();

        $this->assertCount(1, $widgets);
        $this->assertSame('@c975LUi/management/_donovan_dashboard_widget.html.twig', $widgets[0]['template']);
        $this->assertSame([], $widgets[0]['context']);
    }
}
