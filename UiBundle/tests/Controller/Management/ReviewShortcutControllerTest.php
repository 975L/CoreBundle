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
use c975L\UiBundle\Controller\Management\ReviewShortcutController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviewShortcutControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(
        ?Config $config,
        EntityManagerInterface $manager,
        bool $currentlyEnabled,
    ): ReviewShortcutController {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findOneBySlug')->willReturn($config);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');
        $configService->method('getBool')->willReturn($currentlyEnabled);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ReviewShortcutController($repository, $manager, $configService, $translator);
    }

    public function testToggleTurnsTheReviewsOnAndFlushesWhenTokenIsValid(): void
    {
        $config = new Config()->setSlug('ui-enable-reviews')->setValue(false);
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
        $this->assertSame(['flash.reviews_enabled'], $session->getFlashBag()->get('success'));
        $this->assertSame('/management', $response->getTargetUrl());
    }

    // Closing a site back to what visitors write is the same tile, the moderation screen and the public form going away with it (see ReviewService::isEnabled())
    public function testToggleTurnsTheReviewsBackOff(): void
    {
        $config = new Config()->setSlug('ui-enable-reviews')->setValue(true);

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
        $this->assertSame(['flash.reviews_disabled'], $session->getFlashBag()->get('success'));
    }

    public function testToggleDoesNothingWhenCsrfTokenIsInvalid(): void
    {
        $config = new Config()->setSlug('ui-enable-reviews')->setValue(false);
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

    // A site whose "ui-enable-reviews" row was never seeded has nothing to flip, and must not fatal on the way
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

    public function testToggleDeniesAccessWhenNotAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController(null, $this->createStub(EntityManagerInterface::class), currentlyEnabled: false);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->toggle(new Request());
    }
}
