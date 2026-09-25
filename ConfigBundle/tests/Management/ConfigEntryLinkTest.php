<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\ConfigEntryLink;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class ConfigEntryLinkTest extends TestCase
{
    // Answers the admin role the link falls back on for an entry that isn't restricted
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return $configService;
    }

    // Builds a Config entity with its id set, no DB needed
    private function createConfig(int $id, bool $isRestricted = false): Config
    {
        $config = new Config();
        $config->setIsRestricted($isRestricted);

        $reflection = new \ReflectionProperty(Config::class, 'id');
        $reflection->setValue($config, $id);

        return $config;
    }

    // A plain entry is the admin's, a restricted one the super-admin's since it stays out of the list below that role
    public function testRoleIsTheAdminsUnlessTheEntryIsRestricted(): void
    {
        $link = new ConfigEntryLink($this->createStub(AdminUrlGeneratorInterface::class), $this->createConfigService());

        $this->assertSame('ROLE_ADMIN', $link->role($this->createConfig(1)));
        $this->assertSame('ROLE_SUPER_ADMIN', $link->role($this->createConfig(1, true)));
    }

    // The url opens the entry's own edit screen in the Config CRUD
    public function testEditUrlOpensTheEntrysEditScreen(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setController')->with(ConfigCrudController::class)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setEntityId')->with(42)->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/config/42/edit');

        $link = new ConfigEntryLink($adminUrlGenerator, $this->createConfigService());

        $this->assertSame('/management/config/42/edit', $link->editUrl($this->createConfig(42)));
    }
}
