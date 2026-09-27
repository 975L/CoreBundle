<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The code an admin logging in from an unknown browser receives by email, the login waiting in the session until it is typed (see LoginCodeSubscriber and LoginCodeController)
class LoginCode
{
    public const string CONFIG = 'login-admin-code';

    public const string EMAIL_TEMPLATE = 'login_code';

    public const string SESSION = 'config_login_code';

    public const string VALID = 'valid';

    public const string INVALID = 'invalid';

    public const string EXPIRED = 'expired';

    public const string EXHAUSTED = 'exhausted';

    private const int TTL = 600;

    private const int ATTEMPTS = 5;

    private const int RESEND_COOLDOWN = 60;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly EmailService $emailService,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Asked only when the setting is on and the account holds the site's admin role
    public function isRequired(TokenInterface $token): bool
    {
        return true === $this->configService->get(self::CONFIG)
            && $this->accessDecisionManager->decide($token, [(string) $this->configService->get('site-role-admin')]);
    }

    // Sends a code and parks the login until it is typed. False when nothing could be sent: the login then goes through, a mailer down never locking out the admin who would fix it
    public function start(SessionInterface $session, UserInterface $user, string $target): bool
    {
        return $this->issue($session, $user, ['attempts' => 0, 'target' => $target]);
    }

    public function isPending(SessionInterface $session): bool
    {
        return $session->has(self::SESSION);
    }

    // Another code for the same login once the cooldown is over, the failed attempts still counting
    public function resend(SessionInterface $session, UserInterface $user): bool
    {
        $pending = $session->get(self::SESSION);

        return \is_array($pending) && time() - $pending['sent'] >= self::RESEND_COOLDOWN && $this->issue($session, $user, $pending);
    }

    // One of VALID, INVALID, EXPIRED or EXHAUSTED once ATTEMPTS codes were wrong
    public function verify(SessionInterface $session, string $code): string
    {
        $pending = $session->get(self::SESSION);
        if (!\is_array($pending) || $pending['attempts'] >= self::ATTEMPTS) {
            return self::EXHAUSTED;
        }

        if (time() > $pending['expires']) {
            return self::EXPIRED;
        }

        if (hash_equals($pending['hash'], hash('sha256', trim($code)))) {
            return self::VALID;
        }

        $session->set(self::SESSION, [...$pending, 'attempts' => $pending['attempts'] + 1]);

        return $pending['attempts'] + 1 >= self::ATTEMPTS ? self::EXHAUSTED : self::INVALID;
    }

    // Ends the wait, answering where the login was going
    public function finish(SessionInterface $session): string
    {
        $pending = $session->remove(self::SESSION);

        return \is_array($pending) ? $pending['target'] : '/';
    }

    // A fresh code sent, then kept hashed with its expiry
    /** @param array<string, mixed> $pending */
    private function issue(SessionInterface $session, UserInterface $user, array $pending): bool
    {
        $code = \sprintf('%06d', random_int(0, 999999));
        if (!$this->send($user, $code)) {
            return false;
        }

        $session->set(self::SESSION, [...$pending, 'hash' => hash('sha256', $code), 'expires' => time() + self::TTL, 'sent' => time()]);

        return true;
    }

    // The code mailed to the account, false without an address, a template or a mailer answering
    private function send(UserInterface $user, string $code): bool
    {
        $to = method_exists($user, 'getEmail') ? (string) $user->getEmail() : '';
        $html = str_contains($to, '@') ? $this->emailTemplateRenderer->renderNamed(self::EMAIL_TEMPLATE, ['code' => $code]) : null;
        $sent = null !== $html && $this->emailService->send(new EmailSendRequest(
            subject: $this->translator->trans('label.login_code_heading', [], 'config'),
            context: [],
            html: $html,
            to: $to,
        ));

        if (!$sent) {
            $this->logger->warning('Login code not sent to "{user}", the login went through without it.', ['user' => $user->getUserIdentifier()]);
        }

        return $sent;
    }
}
