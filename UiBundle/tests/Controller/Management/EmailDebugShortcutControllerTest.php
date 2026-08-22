<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\EmailDebugShortcutController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailDebugShortcutControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(
        ?Config $config,
        EntityManagerInterface $manager,
        bool $currentlyEnabled,
    ): EmailDebugShortcutController {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findOneBySlug')->willReturn($config);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('getBool')->willReturn($currentlyEnabled);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new EmailDebugShortcutController($repository, $manager, $configService, $translator);
    }

    public function testToggleTurnsTheDebugModeOnAndFlushesWhenTokenIsValid(): void
    {
        $config = new Config()->setSlug('email-debug')->setValue(false);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('flush');

        $controller = $this->createController($config, $manager, currentlyEnabled: false);
        [$requestStack, $session] = $this->createRequestStackWithSession();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $response = $controller->toggle(new Request([], ['_token' => 'valid-token']));

        $this->assertSame('true', $config->getValue());
        $this->assertNotNull($config->getModification());
        $this->assertSame(['flash.email_debug_enabled'], $session->getFlashBag()->get('success'));
        $this->assertSame('/management', $response->getTargetUrl());
    }

    // Turned on for the length of a test, the tile is what turns it back off - and says so
    public function testToggleTurnsTheDebugModeBackOff(): void
    {
        $config = new Config()->setSlug('email-debug')->setValue(true);

        $controller = $this->createController($config, $this->createStub(EntityManagerInterface::class), currentlyEnabled: true);
        [$requestStack, $session] = $this->createRequestStackWithSession();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $controller->toggle(new Request([], ['_token' => 'valid-token']));

        $this->assertSame('false', $config->getValue());
        $this->assertSame(['flash.email_debug_disabled'], $session->getFlashBag()->get('success'));
    }

    public function testToggleDoesNothingWhenCsrfTokenIsInvalid(): void
    {
        $config = new Config()->setSlug('email-debug')->setValue(false);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController($config, $manager, currentlyEnabled: false);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
            'router' => $this->createRouter(),
            'request_stack' => $this->createRequestStackWithSession()[0],
        ]));

        $controller->toggle(new Request([], ['_token' => 'invalid-token']));

        $this->assertSame('false', $config->getValue());
    }

    // A site whose "email-debug" row was never seeded has nothing to flip, and must not fatal on the way
    public function testToggleDoesNothingWhenTheConfigIsMissing(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController(null, $manager, currentlyEnabled: false);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $this->createRequestStackWithSession()[0],
        ]));

        $response = $controller->toggle(new Request([], ['_token' => 'valid-token']));

        $this->assertSame('/management', $response->getTargetUrl());
    }

    public function testToggleDeniesAccessWhenNotSuperAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController(null, $this->createStub(EntityManagerInterface::class), currentlyEnabled: false);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->toggle(new Request());
    }
}
