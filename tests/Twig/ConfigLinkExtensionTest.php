<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use c975L\UiBundle\Twig\ConfigLinkExtension;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class ConfigLinkExtensionTest extends TestCase
{
    private function createConfig(string $slug, int $id): Config
    {
        $config = (new Config())->setSlug($slug);
        (new \ReflectionProperty($config, 'id'))->setValue($config, $id);

        return $config;
    }

    public function testLinksToTheConfigsEditPageWhenTheSlugIsAlreadyLoaded(): void
    {
        $configRepository = $this->createStub(ConfigRepository::class);
        $configRepository->method('findOneBy')->willReturn($this->createConfig('site-form-gdpr', 42));

        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setEntityId')->with(42)->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/config/42/edit');

        $extension = new ConfigLinkExtension($configRepository, new ConfigEditUrlResolver($urlGenerator));

        $this->assertSame('/management/config/42/edit', $extension->configEditUrl('site-form-gdpr'));
    }

    // A slug never loaded into DB falls back to the plain Config list, not a broken entity id
    public function testFallsBackToTheConfigListWhenTheSlugIsNotLoadedYet(): void
    {
        $configRepository = $this->createStub(ConfigRepository::class);
        $configRepository->method('findOneBy')->willReturn(null);

        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->expects($this->never())->method('setEntityId');
        $urlGenerator->method('generateUrl')->willReturn('/management/config');

        $extension = new ConfigLinkExtension($configRepository, new ConfigEditUrlResolver($urlGenerator));

        $this->assertSame('/management/config', $extension->configEditUrl('site-form-gdpr'));
    }
}
