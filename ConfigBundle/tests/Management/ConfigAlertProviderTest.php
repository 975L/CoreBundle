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
use c975L\ConfigBundle\Management\ConfigAlertProvider;
use c975L\ConfigBundle\Management\ConfigLabelResolver;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfigAlertProviderTest extends TestCase
{
    // A resolver over a translator holding the given keys, anything else coming back as the key itself, exactly as Symfony's does
    private function createResolver(array $translations = []): ConfigLabelResolver
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => $translations[$id] ?? $id
        );

        return new ConfigLabelResolver($translator);
    }

    // Builds a Config entity requiring attention (slug/description/severity, no DB needed)
    private function createConfig(int $id, string $slug, string $severity): Config
    {
        $config = new Config();
        $config->setSlug($slug);
        $config->setLabel($slug);
        $config->setSeverity($severity);
        $config->setDescription('description-' . $slug);
        $config->setCreation(new \DateTime());
        $config->setModification(new \DateTime());

        $reflection = new \ReflectionProperty(Config::class, 'id');
        $reflection->setValue($config, $id);

        return $config;
    }

    public function testGetAlertsMapsEachConfigRequiringAttentionToAnAlertWithEditUrl(): void
    {
        $config = $this->createConfig(42, 'site-maintenance-hash', Config::SEVERITY_WARNING);

        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findRequiringAttention')->willReturn([$config]);

        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setController')->with(ConfigCrudController::class)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setEntityId')->with(42)->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/config/42/edit');

        $resolver = $this->createResolver(['label.site_maintenance_hash' => 'Hash de maintenance']);
        $provider = new ConfigAlertProvider($repository, $adminUrlGenerator, $resolver, $this->createConfigService([]), $this->createTranslator());

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('Hash de maintenance', $alerts[0]['label']);
        $this->assertSame('description-site-maintenance-hash', $alerts[0]['description']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
        $this->assertSame('/management/config/42/edit', $alerts[0]['url']);
    }

    // The case an app declaring its own configs in configs.json lands in: no xlf, the label written in clear, and a dashboard that used to show "label.console_digest_mailto"
    public function testGetAlertsFallsBackToTheStoredLabelWhenTheDerivedKeyHasNoTranslation(): void
    {
        $config = $this->createConfig(7, 'console-digest-mailto', Config::SEVERITY_WARNING);
        $config->setLabel('Destinataire du digest des sites');

        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findRequiringAttention')->willReturn([$config]);

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/config/7/edit');

        $provider = new ConfigAlertProvider($repository, $adminUrlGenerator, $this->createResolver(), $this->createConfigService([]), $this->createTranslator());

        $alerts = $provider->getAlerts();

        $this->assertSame('Destinataire du digest des sites', $alerts[0]['label']);
    }

    // A key encrypted with a secret the site no longer has: the entry shows as filled, and everything reading it gets nothing (see ConfigService::loadAll())
    public function testGetAlertsReportsASensitiveConfigFilledButUnreadable(): void
    {
        $unreadable = $this->createConfig(11, 'stripe-secret', Config::SEVERITY_DANGER);
        $readable = $this->createConfig(12, 'stripe-secret-test', Config::SEVERITY_DANGER);

        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findRequiringAttention')->willReturn([]);
        $repository->method('findSensitiveWithValue')->willReturn([$unreadable, $readable]);

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/config/11/edit');

        $provider = new ConfigAlertProvider(
            $repository,
            $adminUrlGenerator,
            $this->createResolver(),
            $this->createConfigService(['stripe-secret-test' => 'sk_test_1']),
            $this->createTranslator(),
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('stripe-secret', $alerts[0]['label']);
        $this->assertSame('description.config_unreadable', $alerts[0]['description']);
        $this->assertSame(Config::SEVERITY_DANGER, $alerts[0]['severity']);
    }

    // The dashboard renders for an editor now, who has no business reading the site's own settings - both kinds of alert carry the admin bar so AlertBuilder drops them for that user
    public function testEveryAlertCarriesTheAdminRole(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findRequiringAttention')->willReturn([$this->createConfig(42, 'site-name', Config::SEVERITY_WARNING)]);
        $repository->method('findSensitiveWithValue')->willReturn([$this->createConfig(11, 'stripe-secret', Config::SEVERITY_DANGER)]);

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/config/42/edit');

        $provider = new ConfigAlertProvider(
            $repository,
            $adminUrlGenerator,
            $this->createResolver(),
            $this->createConfigService(['site-role-admin' => 'ROLE_ADMIN']),
            $this->createTranslator(),
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(2, $alerts);
        $this->assertSame('ROLE_ADMIN', $alerts[0]['role']);
        $this->assertSame('ROLE_ADMIN', $alerts[1]['role']);
    }

    // The configuration as the site reads it: a sensitive value it could not decrypt is empty here while the row still holds its ciphertext
    private function createConfigService(array $values): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => $values[$slug] ?? null);

        return $configService;
    }

    // The stub hands the key back untranslated, which is what the assertions read
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    public function testGetAlertsReturnsEmptyArrayWhenNoConfigRequiresAttention(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findRequiringAttention')->willReturn([]);

        $provider = new ConfigAlertProvider($repository, $this->createStub(AdminUrlGeneratorInterface::class), $this->createResolver(), $this->createConfigService([]), $this->createTranslator());

        $this->assertSame([], $provider->getAlerts());
    }
}
