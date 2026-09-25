<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\MenuEntryResolver;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MenuEntryResolverTest extends TestCase
{
    // Answers the admin role a menu declaring none falls back on
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return $configService;
    }

    // Grants only the roles given, so each test says which bar the user clears
    private function createSecurity(array $grantedRoles): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(fn (string $role) => \in_array($role, $grantedRoles, true));

        return $security;
    }

    private function createResolver(array $grantedRoles = [], ?AdminUrlGeneratorInterface $adminUrlGenerator = null, ?UrlGeneratorInterface $urlGenerator = null): MenuEntryResolver
    {
        return new MenuEntryResolver(
            $adminUrlGenerator ?? $this->createStub(AdminUrlGeneratorInterface::class),
            $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
            $this->createSecurity($grantedRoles),
            $this->createConfigService(),
        );
    }

    // A menu falls back on the admin role, a link naming no role is open to anyone reaching the back office
    public function testIsGrantedAppliesTheSidebarsDefaults(): void
    {
        $resolver = $this->createResolver();

        $this->assertFalse($resolver->isGranted(['controller' => 'MyCrudController']));
        $this->assertTrue($resolver->isGranted(['name' => 'my_route']));
        $this->assertFalse($resolver->isGranted(['name' => 'my_route', 'role' => 'ROLE_EDITOR']));

        $editor = $this->createResolver(['ROLE_EDITOR']);

        $this->assertTrue($editor->isGranted(['controller' => 'MyCrudController', 'role' => 'ROLE_EDITOR']));
        $this->assertFalse($editor->isGranted(['controller' => 'MyCrudController']));
    }

    // A menu opens its controller's index unless it names another action
    public function testUrlOfAMenuOpensItsActionDefaultingToIndex(): void
    {
        $actions = [];
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnCallback(function (string $action) use (&$actions, $adminUrlGenerator) {
            $actions[] = $action;

            return $adminUrlGenerator;
        });
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/my-entity');

        $resolver = $this->createResolver([], $adminUrlGenerator);

        $this->assertSame('/management/my-entity', $resolver->url(['controller' => 'MyCrudController']));
        $this->assertSame('/management/my-entity', $resolver->url(['controller' => 'MyCrudController', 'action' => 'overview']));
        $this->assertSame([Action::INDEX, 'overview'], $actions);
    }

    // A link's literal url wins over its route, and a route is resolved absolute only when the link leaves the admin
    public function testUrlOfALinkPrefersItsLiteralUrlThenItsRoute(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(fn (string $name, array $parameters, int $referenceType) => UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://example.com/' . $name : '/' . $name);

        $resolver = $this->createResolver([], null, $urlGenerator);

        $this->assertSame('https://elsewhere.com', $resolver->url(['url' => 'https://elsewhere.com', 'name' => 'my_route']));
        $this->assertSame('/my_route', $resolver->url(['name' => 'my_route']));
        $this->assertSame('https://example.com/my_route', $resolver->url(['name' => 'my_route', 'target' => '_blank']));
    }
}
