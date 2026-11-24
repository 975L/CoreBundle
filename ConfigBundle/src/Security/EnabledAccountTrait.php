<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Contract\UserInterface;

// Whether an account is enabled, the one way the token managers read it: their stateless firewalls have no user checker to do it
trait EnabledAccountTrait
{
    // Read from isEnabled() even on a User that does not implement InactivityAwareInterface, the scaffolded ones all carrying it
    private static function isEnabled(UserInterface $user): bool
    {
        if ($user instanceof InactivityAwareInterface) {
            return $user->isEnabled();
        }

        return !method_exists($user, 'isEnabled') || true === $user->isEnabled();
    }
}
