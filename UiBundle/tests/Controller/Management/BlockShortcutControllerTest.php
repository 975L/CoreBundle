<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\UiBundle\Controller\Management\BlockShortcutController;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockShortcutControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(BlockCacheInvalidator $blockCacheInvalidator): BlockShortcutController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BlockShortcutController($blockCacheInvalidator, $translator);
    }

    public function testClearCacheInvalidatesCacheAndAddsFlashWhenTokenIsValid(): void
    {
        $blockCacheInvalidator = $this->createMock(BlockCacheInvalidator::class);
        $blockCacheInvalidator->expects($this->once())->method('invalidateAll');

        $controller = $this->createController($blockCacheInvalidator);
        [$requestStack, $session] = $this->createRequestStackWithSession();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $response = $controller->clearCache(new Request([], ['_token' => 'valid-token']));

        $this->assertSame(['flash.block_cache_cleared'], $session->getFlashBag()->get('success'));
        $this->assertSame('/management', $response->getTargetUrl());
    }

    public function testClearCacheDoesNothingWhenCsrfTokenIsInvalid(): void
    {
        $blockCacheInvalidator = $this->createMock(BlockCacheInvalidator::class);
        $blockCacheInvalidator->expects($this->never())->method('invalidateAll');

        $controller = $this->createController($blockCacheInvalidator);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
            'router' => $this->createRouter(),
            'request_stack' => $this->createRequestStackWithSession()[0],
        ]));

        $controller->clearCache(new Request([], ['_token' => 'invalid-token']));
    }

    public function testClearCacheDeniesAccessWhenNotGranted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController($this->createStub(BlockCacheInvalidator::class));
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->clearCache(new Request());
    }
}
