<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Bundle\SecurityBundle\Security;

// Whether an address typed into a public form is the one of the account signed in, the only proof a form has: a visitor can type anybody's. An account never confirmed cannot sign in (see UserChecker), so a signed-in address has been proven
class CurrentAccountAddress
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    // The address of the account signed in, as it stands: what a form offers to save typing it. The app's own User declares getEmail(), which no interface of this ecosystem does: checked rather than assumed, an account without one having no address
    public function address(): ?string
    {
        $user = $this->security->getUser();
        $address = null !== $user && method_exists($user, 'getEmail') ? $user->getEmail() : null;

        return \is_string($address) && '' !== trim($address) ? $address : null;
    }

    // Whether this address is the one of the account signed in, whatever its case or surrounding spaces
    public function owns(?string $email): bool
    {
        $address = $this->address();

        return null !== $address && self::normalize($address) === self::normalize($email);
    }

    private static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }
}
