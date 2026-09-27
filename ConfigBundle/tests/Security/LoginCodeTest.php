<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Security\LoginCode;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginCodeTest extends TestCase
{
    /** @var list<string> */
    private array $codes = [];

    // Asked of an admin only, and only once the setting is on
    public function testRequiredForAnAdminWhenTheSettingIsOn(): void
    {
        $token = $this->createStub(TokenInterface::class);

        $this->assertTrue($this->loginCode(enabled: true, admin: true)->isRequired($token));
        $this->assertFalse($this->loginCode(enabled: true, admin: false)->isRequired($token));
        $this->assertFalse($this->loginCode(enabled: false, admin: true)->isRequired($token));
    }

    // The code mailed is the one accepted, the login then going on to where it was heading
    public function testTheCodeMailedIsAccepted(): void
    {
        $session = $this->session();
        $loginCode = $this->loginCode();

        $this->assertTrue($loginCode->start($session, new UserStub(), '/management'));
        $this->assertTrue($loginCode->isPending($session));
        $this->assertSame(LoginCode::INVALID, $loginCode->verify($session, '000000' === $this->codes[0] ? '111111' : '000000'));
        $this->assertSame(LoginCode::VALID, $loginCode->verify($session, $this->codes[0]));
        $this->assertSame('/management', $loginCode->finish($session));
        $this->assertFalse($loginCode->isPending($session));
    }

    // Five wrong codes end the attempt, the right one included afterwards
    public function testFiveWrongCodesExhaustTheLogin(): void
    {
        $session = $this->session();
        $loginCode = $this->loginCode();
        $loginCode->start($session, new UserStub(), '/');
        $wrong = '000000' === $this->codes[0] ? '111111' : '000000';

        for ($i = 1; $i < 5; ++$i) {
            $this->assertSame(LoginCode::INVALID, $loginCode->verify($session, $wrong));
        }

        $this->assertSame(LoginCode::EXHAUSTED, $loginCode->verify($session, $wrong));
        $this->assertSame(LoginCode::EXHAUSTED, $loginCode->verify($session, $this->codes[0]));
    }

    // A code past its ten minutes is refused
    public function testAnExpiredCodeIsRefused(): void
    {
        $session = $this->session();
        $loginCode = $this->loginCode();
        $loginCode->start($session, new UserStub(), '/');
        $session->set(LoginCode::SESSION, [...$session->get(LoginCode::SESSION), 'expires' => time() - 1]);

        $this->assertSame(LoginCode::EXPIRED, $loginCode->verify($session, $this->codes[0]));
    }

    // No email sent, no wait: a mailer down never locks the admin out
    public function testNothingPendingWhenTheEmailFails(): void
    {
        $session = $this->session();

        $this->assertFalse($this->loginCode(sends: false)->start($session, new UserStub(), '/'));
        $this->assertFalse($session->has(LoginCode::SESSION));
    }

    // Another code only once the minute is over, replacing the first
    public function testResendWaitsAMinute(): void
    {
        $session = $this->session();
        $loginCode = $this->loginCode();
        $loginCode->start($session, new UserStub(), '/');

        $this->assertFalse($loginCode->resend($session, new UserStub()));

        $session->set(LoginCode::SESSION, [...$session->get(LoginCode::SESSION), 'sent' => time() - 60]);
        $this->assertTrue($loginCode->resend($session, new UserStub()));
        $this->assertSame(LoginCode::VALID, $loginCode->verify($session, $this->codes[1]));
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    // Over that setting and role, the codes mailed collected
    private function loginCode(bool $enabled = true, bool $admin = true, bool $sends = true): LoginCode
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => LoginCode::CONFIG === $key ? $enabled : 'ROLE_ADMIN');

        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturn($admin);

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturnCallback(fn (string $name, array $variables): string => $variables['code']);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request) use ($sends): bool {
            $this->codes[] = (string) $request->html;

            return $sends;
        });

        return new LoginCode($configService, $accessDecisionManager, $emailService, $renderer, $this->createStub(TranslatorInterface::class), new NullLogger());
    }
}
