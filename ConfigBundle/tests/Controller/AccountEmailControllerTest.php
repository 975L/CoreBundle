<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\AccountEmailController;
use c975L\ConfigBundle\Service\EmailChanger;
use c975L\ConfigBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;

class AccountEmailControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private Session $session;

    // The link is bound to the account that asked for it, so the member is logged in to follow it
    public function testRequiresAMember(): void
    {
        $attributes = new \ReflectionClass(AccountEmailController::class)->getAttributes(IsGranted::class);

        $this->assertSame('ROLE_USER', $attributes[0]->newInstance()->attribute);
    }

    // Each outcome said on the account page: changed, taken in the meantime, or the link refused
    public function testEachOutcomeIsToldOnTheAccountPage(): void
    {
        $this->assertSame(['success' => ['flash.email_changed']], $this->follow(static fn (): bool => true));
        $this->assertSame(['danger' => ['flash.email_taken']], $this->follow(static fn (): bool => false));
        $this->assertSame(['danger' => [new ExpiredSignatureException()->getReason()]], $this->follow(static fn (): bool => throw new ExpiredSignatureException()));
    }

    // The flashes left once the link is followed, EmailChanger::confirm() answering through $outcome
    /** @return array<string, list<string>> */
    private function follow(\Closure $outcome): array
    {
        $emailChanger = $this->createStub(EmailChanger::class);
        $emailChanger->method('confirm')->willReturnCallback($outcome);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $user = new UserStub();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $controller = new AccountEmailController($emailChanger, $translator);
        $controller->setContainer($this->createContainer([
            'request_stack' => new RequestStack([$request]),
            'router' => $this->createRouter('/account'),
            'security.token_storage' => $tokenStorage,
        ]));

        $response = $controller->confirm($request);
        $this->assertSame('/account', $response->headers->get('Location'));

        return $this->session->getFlashBag()->all();
    }
}
