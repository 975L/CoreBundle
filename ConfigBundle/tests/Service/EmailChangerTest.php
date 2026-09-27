<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\EmailChanger;
use c975L\ConfigBundle\Service\EmailVerifier;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailChangerTest extends TestCase
{
    /** @var list<EmailSendRequest> */
    private array $sent = [];

    // A free address gets the link, the new address riding in the signed url
    public function testAFreeAddressIsSentTheSignedLink(): void
    {
        $user = new UserStub('old@example.test')->withId(42);

        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->once())->method('sendSignedLink')
            ->with(EmailChanger::CONFIRM_TEMPLATE, EmailChanger::CONFIRM_ROUTE, $user, ['email' => 'new@example.test'], 'label.account_email_change_heading', 'new@example.test', 'old@example.test');

        $this->changer(emailVerifier: $emailVerifier)->request($user, 'new@example.test');
    }

    // An address another account holds is sent nothing, the page answering the same as for a free one
    public function testATakenAddressIsSentNothing(): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->never())->method('sendSignedLink');

        $this->changer(emailVerifier: $emailVerifier, taken: true)->request(new UserStub()->withId(42), 'taken@example.test');
    }

    // The checked link swaps the address, then tells the old one where the account went
    public function testAValidLinkSwapsTheAddressAndTellsTheOldOne(): void
    {
        $user = new UserStub('old@example.test')->withId(42);

        $this->assertTrue($this->changer($this->entityManager(false, flushes: true))->confirm(Request::create('/account/email/confirm?email=new@example.test'), $user));
        $this->assertSame('new@example.test', $user->getUserIdentifier());
        $this->assertCount(1, $this->sent);
        $this->assertSame('old@example.test', $this->sent[0]->to);
    }

    // On a site logging in by username, the link is bound to the address and the notice reaches the old mailbox, not the username
    public function testAUsernameLoginIsBoundToTheAddress(): void
    {
        $user = new class ('old@example.test') extends UserStub {
            public function getUserIdentifier(): string
            {
                return 'jdoe';
            }
        };
        $user->withId(42);

        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->once())->method('sendSignedLink')
            ->with($this->anything(), $this->anything(), $user, $this->anything(), $this->anything(), 'new@example.test', 'old@example.test');
        $this->changer(emailVerifier: $emailVerifier)->request($user, 'new@example.test');

        $this->assertTrue($this->changer($this->entityManager(false, flushes: true))->confirm(Request::create('/account/email/confirm?email=new@example.test'), $user));
        $this->assertSame('new@example.test', $user->getEmail());
        $this->assertSame('old@example.test', $this->sent[0]->to);
    }

    // Taken in between by another account, the address is left as it was
    public function testAnAddressTakenInTheMeantimeIsNotSwapped(): void
    {
        $user = new UserStub('old@example.test')->withId(42);

        $this->assertFalse($this->changer(taken: true)->confirm(Request::create('/account/email/confirm?email=new@example.test'), $user));
        $this->assertSame('old@example.test', $user->getUserIdentifier());
        $this->assertSame([], $this->sent);
    }

    // An altered, expired or used link changes nothing, the helper's exception reaching the controller
    public function testARefusedLinkChangesNothing(): void
    {
        $user = new UserStub('old@example.test')->withId(42);

        $this->expectException(ExpiredSignatureException::class);

        try {
            $this->changer(valid: false)->confirm(Request::create('/account/email/confirm?email=new@example.test'), $user);
        } finally {
            $this->assertSame('old@example.test', $user->getUserIdentifier());
        }
    }

    // What the repository answers for an address, and whether the swap is expected to be saved
    private function entityManager(bool $taken, bool $flushes = false): EntityManagerInterface
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($taken ? new UserStub() : null);

        $entityManager = $flushes ? $this->createMock(EntityManagerInterface::class) : $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        if ($flushes) {
            $entityManager->expects($this->once())->method('flush');
        }

        return $entityManager;
    }

    // The helper hand-written, validateEmailConfirmationFromRequest() being declared through @method only (see EmailVerifierTest)
    private function changer(?EntityManagerInterface $entityManager = null, ?EmailVerifier $emailVerifier = null, bool $taken = false, bool $valid = true): EmailChanger
    {
        $verifyEmailHelper = new readonly class ($valid) implements VerifyEmailHelperInterface {
            public function __construct(private bool $valid)
            {
            }

            /** @param array<string, mixed> $extraParams */
            public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
            {
                throw new \LogicException('Not expected to be called.');
            }

            public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
            {
                throw new \LogicException('Not expected to be called.');
            }

            public function validateEmailConfirmationFromRequest(Request $request, string $userId, string $userEmail): void
            {
                if (!$this->valid) {
                    throw new ExpiredSignatureException();
                }
            }
        };

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request): bool {
            $this->sent[] = $request;

            return true;
        });

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturn('<html>notice</html>');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new EmailChanger(
            $emailVerifier ?? $this->createStub(EmailVerifier::class),
            $verifyEmailHelper,
            $emailService,
            $renderer,
            $entityManager ?? $this->entityManager($taken),
            $translator,
        );
    }
}
