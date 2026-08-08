<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use c975L\UiBundle\Controller\Management\StylesheetShortcutController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class StylesheetShortcutControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(StylesheetCacheWarmer $stylesheetCacheWarmer): StylesheetShortcutController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new StylesheetShortcutController($stylesheetCacheWarmer, $translator);
    }

    public function testCompileRebuildsStylesheetsAndAddsFlashWhenTokenIsValid(): void
    {
        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $controller = $this->createController($stylesheetCacheWarmer);
        [$requestStack, $session] = $this->createRequestStackWithSession();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $response = $controller->compile(new Request([], ['_token' => 'valid-token']));

        $this->assertSame(['flash.stylesheet_compiled'], $session->getFlashBag()->get('success'));
        $this->assertSame('/management', $response->getTargetUrl());
    }

    public function testCompileDoesNothingWhenCsrfTokenIsInvalid(): void
    {
        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->never())->method('compileAll');

        $controller = $this->createController($stylesheetCacheWarmer);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
            'router' => $this->createRouter(),
            'request_stack' => $this->createRequestStackWithSession()[0],
        ]));

        $controller->compile(new Request([], ['_token' => 'invalid-token']));
    }

    public function testCompileDeniesAccessWhenNotGranted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController($this->createStub(StylesheetCacheWarmer::class));
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->compile(new Request());
    }
}
