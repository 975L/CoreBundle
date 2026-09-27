<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\LoginCodeShortcutController;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Tests\Repository\ConfigRepositoryFindOneBySlugFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginCodeShortcutControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // The tile flips the setting and says so
    public function testFlipsTheSettingWhenTheTokenIsValid(): void
    {
        $config = new Config()->setSlug('login-admin-code')->setValue(false);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('flush');

        $this->controller($config, $manager, validToken: true)->toggle(new Request([], ['_token' => 'valid-token']));

        $this->assertSame('true', $config->getValue());
    }

    // A forged post changes nothing
    public function testChangesNothingWithAnInvalidToken(): void
    {
        $config = new Config()->setSlug('login-admin-code')->setValue(false);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('flush');

        $response = $this->controller($config, $manager, validToken: false)->toggle(new Request([], ['_token' => 'forged']));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('false', $config->getValue());
    }

    private function controller(Config $config, EntityManagerInterface $manager, bool $validToken): LoginCodeShortcutController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');
        $configService->method('getBool')->willReturn(false);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $controller = new LoginCodeShortcutController(new ConfigRepositoryFindOneBySlugFixture($config), $manager, $configService, $translator);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager($validToken),
            'router' => $this->createRouter(),
            'request_stack' => $this->createRequestStackWithSession()[0],
        ]));

        return $controller;
    }
}
