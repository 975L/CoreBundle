<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Account\AccountDataCollector;
use c975L\ConfigBundle\Account\AccountDataProviderInterface;
use c975L\ConfigBundle\Controller\AccountExportController;
use c975L\ConfigBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AccountExportControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // A remembered session does not hand personal data out
    public function testRequiresAFullyAuthenticatedUser(): void
    {
        $attributes = new \ReflectionMethod(AccountExportController::class, 'export')->getAttributes(IsGranted::class);

        $this->assertSame('IS_AUTHENTICATED_FULLY', $attributes[0]->newInstance()->attribute);
    }

    // The export downloaded as a JSON file named after the day
    public function testDownloadsTheDataAsAJsonFile(): void
    {
        $provider = $this->createStub(AccountDataProviderInterface::class);
        $provider->method('getAccountData')->willReturn(['profile' => ['email' => 'user@example.test']]);

        $user = new UserStub();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        $controller = new AccountExportController(new AccountDataCollector([$provider]));
        $controller->setContainer($this->createContainer(['security.token_storage' => $tokenStorage]));

        $response = $controller->export();

        $this->assertSame(['profile' => ['email' => 'user@example.test']], json_decode((string) $response->getContent(), true));
        $this->assertSame('attachment; filename=account-data-' . date('Y-m-d') . '.json', $response->headers->get('Content-Disposition'));
    }
}
