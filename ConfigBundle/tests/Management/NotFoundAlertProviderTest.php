<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\NotFoundAlertProvider;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotFoundAlertProviderTest extends TestCase
{
    private function createProvider(int $internalCount): NotFoundAlertProvider
    {
        $repository = $this->createStub(NotFoundRepository::class);
        $repository->method('countInternal')->willReturn($internalCount);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/not-found');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new NotFoundAlertProvider($repository, $configService, $adminUrlGenerator, $translator);
    }

    // The point of the whole feature: a broken link on our own pages says so on the dashboard rather than waiting to be reported
    public function testAlertsOnTheSitesOwnBrokenLinks(): void
    {
        $alerts = $this->createProvider(3)->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.not_found_alert', $alerts[0]['label']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
        $this->assertSame('ROLE_EDITOR', $alerts[0]['role']);
        $this->assertSame('/management/not-found', $alerts[0]['url']);
    }

    // Only the internal ones are counted, so a table holding nothing but stale links other sites publish stays silent
    public function testStaysSilentWithoutAnInternalBrokenLink(): void
    {
        $this->assertSame([], $this->createProvider(0)->getAlerts());
    }
}
