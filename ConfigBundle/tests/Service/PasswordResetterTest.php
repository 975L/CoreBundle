<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\PasswordResetter;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordResetterTest extends TestCase
{
    public function testResetPasswordHashesAndFlushes(): void
    {
        $user = new UserStub('user@example.test');

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('new-hashed-password');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $resetter = $this->resetter($passwordHasher, $entityManager);
        $resetter->resetPassword($user, 'NewStr0ngPassword!');

        $this->assertSame('new-hashed-password', $user->getPassword());
        $this->assertNotNull($user->getModification());
    }

    // The account's own address is told, so a password changed by somebody else does not go unnoticed
    public function testTheAccountIsToldItsPasswordChanged(): void
    {
        $sent = [];
        $this->resetter(sent: $sent)->resetPassword(new UserStub('user@example.test'), 'NewStr0ngPassword!');

        $this->assertCount(1, $sent);
        $this->assertSame('user@example.test', $sent[0]->to);
    }

    // The same password hashed again (the "other devices" sign-out) tells nobody anything
    public function testNothingIsSentWhenAskedNotTo(): void
    {
        $sent = [];
        $this->resetter(sent: $sent)->resetPassword(new UserStub('user@example.test'), 'NewStr0ngPassword!', notify: false);

        $this->assertSame([], $sent);
    }

    // A template deleted from the back office sends nothing rather than an empty email
    public function testNothingIsSentWithoutTheTemplate(): void
    {
        $sent = [];
        $this->resetter(sent: $sent, html: null)->resetPassword(new UserStub('user@example.test'), 'NewStr0ngPassword!');

        $this->assertSame([], $sent);
    }

    // Collaborators stubbed to succeed, the emails leaving collected in $sent
    /** @param list<EmailSendRequest> $sent */
    private function resetter(?UserPasswordHasherInterface $passwordHasher = null, ?EntityManagerInterface $entityManager = null, ?array &$sent = null, ?string $html = '<html>notice</html>'): PasswordResetter
    {
        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturn($html);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(static function (EmailSendRequest $request) use (&$sent): bool {
            $sent[] = $request;

            return true;
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new PasswordResetter(
            $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $emailService,
            $renderer,
            $translator,
        );
    }
}
