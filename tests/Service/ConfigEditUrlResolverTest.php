<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Entity\Config;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class ConfigEditUrlResolverTest extends TestCase
{
    private function createConfig(int $id): Config
    {
        $config = new Config();
        (new \ReflectionProperty($config, 'id'))->setValue($config, $id);

        return $config;
    }

    public function testResolvesToTheEditUrlWhenConfigIsGiven(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setEntityId')->with(42)->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/config/42/edit');

        $resolver = new ConfigEditUrlResolver($urlGenerator);

        $this->assertSame('/management/config/42/edit', $resolver->resolve($this->createConfig(42)));
    }

    // A null Config (slug not yet loaded into DB) falls back to the plain Config list rather than a broken/nonexistent entity id
    public function testFallsBackToTheListUrlWhenConfigIsNull(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->expects($this->never())->method('setEntityId');
        $urlGenerator->method('generateUrl')->willReturn('/management/config');

        $resolver = new ConfigEditUrlResolver($urlGenerator);

        $this->assertSame('/management/config', $resolver->resolve(null));
    }
}
