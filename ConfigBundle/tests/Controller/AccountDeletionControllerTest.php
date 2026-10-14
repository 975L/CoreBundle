<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\AccountDeletionController;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

// The public page a store links to: open to anyone, naming the site
class AccountDeletionControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // A store's reviewer is not signed in: no access check on the page
    public function testIsOpenToAnyone(): void
    {
        $this->assertSame([], new \ReflectionMethod(AccountDeletionController::class, 'deletion')->getAttributes(IsGranted::class));
    }

    // The site's name reaches the template, which the store asks the page to show
    public function testRendersTheSiteName(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-name', 'Papa Câlin']]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@c975LConfig/account/deletion.html.twig', ['siteName' => 'Papa Câlin'])
            ->willReturn('page');

        $controller = new AccountDeletionController($configService);
        $controller->setContainer($this->createContainer(['twig' => $twig]));

        $this->assertSame('page', $controller->deletion()->getContent());
    }
}
