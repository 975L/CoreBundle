<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// The browser an admin confirmed a login code on, remembered by a cookie signed with the app secret rather than a table: the password hash is part of the signature, so changing it forgets every device
class TrustedDevice
{
    public const string COOKIE = 'c975l_trusted_device';

    public const int DAYS = 30;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
    ) {
    }

    // Whether this browser carries a mark still valid for that account
    public function isTrusted(Request $request, UserInterface $user): bool
    {
        [$expires, $signature] = array_pad(explode('.', (string) $request->cookies->get(self::COOKIE, ''), 2), 2, '');

        return ctype_digit($expires) && (int) $expires > time() && hash_equals($this->sign($user, (int) $expires), $signature);
    }

    // The mark to set once the code was typed, for DAYS days
    public function cookie(Request $request, UserInterface $user): Cookie
    {
        $expires = time() + self::DAYS * 86400;

        return Cookie::create(self::COOKIE, $expires . '.' . $this->sign($user, $expires), $expires, '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
    }

    // The account, the expiry and the password hash, signed together
    private function sign(UserInterface $user, int $expires): string
    {
        $password = $user instanceof PasswordAuthenticatedUserInterface ? (string) $user->getPassword() : '';

        return hash_hmac('sha256', $user->getUserIdentifier() . '|' . $expires . '|' . $password, $this->secret);
    }
}
