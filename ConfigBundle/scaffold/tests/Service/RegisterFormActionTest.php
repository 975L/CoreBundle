<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegisterFormAction;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\EmailVerifier;
use c975L\ConfigBundle\Service\UserRegistrar;
use c975L\UiBundle\Entity\Form;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterFormActionTest extends TestCase
{
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'email-from' => 'noreply@example.test',
                'email-from-name' => 'Example',
                'site-name' => 'Example',
                default => null,
            }
        );

        return $configService;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    private function createAction(UserRepository $userRepository, UserRegistrar $userRegistrar, ?EmailVerifier $emailVerifier = null): RegisterFormAction
    {
        return new RegisterFormAction(
            $userRepository,
            $userRegistrar,
            $emailVerifier ?? $this->createStub(EmailVerifier::class),
            $this->createConfigService(),
            $this->createTranslator(),
        );
    }

    public function testGetKeyReturnsRegister(): void
    {
        $action = $this->createAction($this->createStub(UserRepository::class), $this->createStub(UserRegistrar::class));

        $this->assertSame('register', $action->getKey());
    }

    // Silently succeeds (same generic "form_submitted" flash as a real success) without creating anything nor mailing the holder of a settled account - never reveals which emails are already registered
    public function testHandleReturnsTrueSilentlyWithoutRegisteringWhenEmailAlreadyExists(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(new User()->setIsVerified(true));

        $userRegistrar = $this->createMock(UserRegistrar::class);
        $userRegistrar->expects($this->never())->method('register');

        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->never())->method('sendEmailConfirmation');

        $action = $this->createAction($userRepository, $userRegistrar, $emailVerifier);

        $this->assertTrue($action->handle(new Form(), ['email' => 'taken@example.test', 'plainPassword' => 'Str0ng!Password']));
    }

    // The only way out of an account left unverified by an undelivered or expired link - registering again sends a new confirmation, without touching the existing account
    public function testHandleResendsConfirmationWhenTheExistingAccountIsNotVerifiedYet(): void
    {
        $existing = new User()->setEmail('pending@example.test');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($existing);

        $userRegistrar = $this->createMock(UserRegistrar::class);
        $userRegistrar->expects($this->never())->method('register');

        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->once())->method('sendEmailConfirmation')->with(
            'app_verify_email',
            $existing,
            'Example - label.confirm_your_email',
            'pending@example.test',
        );

        $action = $this->createAction($userRepository, $userRegistrar, $emailVerifier);

        $this->assertTrue($action->handle(new Form(), ['email' => 'pending@example.test', 'plainPassword' => 'Str0ng!Password']));
    }

    // An account an administrator disabled stays disabled: isVerified, not isEnabled, decides - otherwise registering again would mail a link re-enabling it
    public function testHandleDoesNotResendConfirmationForAVerifiedButDisabledAccount(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(new User()->setIsVerified(true)->setIsEnabled(false));

        $userRegistrar = $this->createMock(UserRegistrar::class);
        $userRegistrar->expects($this->never())->method('register');

        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->never())->method('sendEmailConfirmation');

        $action = $this->createAction($userRepository, $userRegistrar, $emailVerifier);

        $this->assertTrue($action->handle(new Form(), ['email' => 'banned@example.test', 'plainPassword' => 'Str0ng!Password']));
    }

    // A field renamed or deleted in the back-office is a configuration error, and it names itself instead of dying as an "Undefined array key" then a TypeError inside UserRegistrar
    public function testHandleThrowsWhenAnExpectedFieldIsMissing(): void
    {
        $userRegistrar = $this->createMock(UserRegistrar::class);
        $userRegistrar->expects($this->never())->method('register');

        $action = $this->createAction($this->createStub(UserRepository::class), $userRegistrar);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must keep a field named "plainPassword"');

        $action->handle(new Form(), ['email' => 'new@example.test']);
    }

    public function testHandleRegistersNewUserAndReturnsTrueWhenEmailIsFree(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $userRegistrar = $this->createMock(UserRegistrar::class);
        $userRegistrar->expects($this->once())->method('register')->with(
            $this->callback(static fn (User $user): bool => 'new@example.test' === $user->getEmail()),
            'Str0ng!Password',
            'app_verify_email',
            'Example - label.confirm_your_email',
            'new@example.test',
        );

        $action = $this->createAction($userRepository, $userRegistrar);

        $this->assertTrue($action->handle(new Form(), ['email' => 'new@example.test', 'plainPassword' => 'Str0ng!Password']));
    }
}
