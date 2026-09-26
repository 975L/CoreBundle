<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Controller\AccountDeleteController;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// The action run through a real form and validator, the collaborators around it mocked: what reaches the database, the event and the session is what is asserted. The firewall itself, sending an anonymous visitor to the login page, is covered by the scaffold's functional AccountDeleteControllerTest
class AccountDeleteControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private const string EMAIL = 'user@example.test';

    private Session $session;

    // A remembered session is not enough to erase an account
    public function testRequiresAFullyAuthenticatedUser(): void
    {
        $attributes = new \ReflectionMethod(AccountDeleteController::class, 'delete')->getAttributes(IsGranted::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('IS_AUTHENTICATED_FULLY', $attributes[0]->newInstance()->attribute);
    }

    // A User the bundle cannot anonymize has no page here
    public function testAnswersNotFoundWhenTheUserCannotBeAnonymized(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->createStub(UserInterface::class))->delete(Request::create('/account/delete'));
    }

    // The site's owner cannot erase the one account able to hand ROLE_SUPER_ADMIN back
    public function testDeniesASuperAdmin(): void
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn(self::EMAIL);
        $user->expects($this->never())->method('anonymize');

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (mixed $attribute) => 'ROLE_SUPER_ADMIN' === $attribute);

        $this->expectException(AccessDeniedException::class);

        $this->controller($user, security: $security)->delete($this->submit(self::EMAIL));
    }

    public function testShowsThePage(): void
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn(self::EMAIL);
        $user->expects($this->never())->method('anonymize');

        $response = $this->controller($user)->delete(Request::create('/account/delete'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('page', $response->getContent());
    }

    // Another address changes nothing: no anonymization, no event, no flush, no logout
    public function testAWrongEmailChangesNothing(): void
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn(self::EMAIL);
        $user->expects($this->never())->method('anonymize');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('logout');

        $response = $this->controller($user, $entityManager, $eventDispatcher, $security)->delete($this->submit('someone-else@example.test'));

        $this->assertSame(422, $response->getStatusCode());
    }

    // The account's own address, typed with other capitals and spaces, anonymizes it as the cleanup command does, then logs its owner out
    public function testTheRightEmailAnonymizesTheAccountAndLogsOut(): void
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn(self::EMAIL);
        $user->expects($this->once())->method('anonymize');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with($this->callback(
            static fn (object $event) => $event instanceof UserAnonymizedEvent && $event->user === $user
        ))->willReturnArgument(0);
        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('logout')->with(false);

        $response = $this->controller($user, $entityManager, $eventDispatcher, $security)->delete($this->submit(' User@Example.test '));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->headers->get('Location'));
        $this->assertSame(['flash.account_deleted'], $this->session->getFlashBag()->get('success'));
    }

    // The firewall's logout response is the one sent back, so the cookies its logout clears are cleared here too
    public function testKeepsTheLogoutResponse(): void
    {
        $user = $this->createStub(InactivityAwareInterface::class);
        $user->method('getEmail')->willReturn(self::EMAIL);

        $logoutResponse = new RedirectResponse('/logged-out');
        $logoutResponse->headers->clearCookie('REMEMBERME');
        $security = $this->createStub(Security::class);
        $security->method('logout')->willReturn($logoutResponse);

        $response = $this->controller($user, security: $security)->delete($this->submit(self::EMAIL));

        $this->assertSame($logoutResponse, $response);
        $this->assertInstanceOf(Cookie::class, $response->headers->getCookies()[0]);
        $this->assertSame(['flash.account_deleted'], $this->session->getFlashBag()->get('success'));
    }

    // The confirmation as the page posts it
    private function submit(string $email): Request
    {
        return Request::create('/account/delete', 'POST', ['account_delete' => ['email' => $email]]);
    }

    // A real form factory with the validator, the rest stubbed unless a test mocks it
    private function controller(
        UserInterface $user,
        ?EntityManagerInterface $entityManager = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?Security $security = null,
    ): AccountDeleteController {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('page');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack([$request]);

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        $controller = new AccountDeleteController(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $security ?? $this->createStub(Security::class),
            $translator,
        );
        $controller->setContainer($this->createContainer([
            'form.factory' => $formFactory,
            'request_stack' => $requestStack,
            'security.token_storage' => $tokenStorage,
            'twig' => $twig,
        ]));

        return $controller;
    }
}
