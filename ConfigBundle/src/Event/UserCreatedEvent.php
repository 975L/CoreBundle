<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Event;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Security\Core\User\UserInterface;

// Dispatched once a visitor's account becomes usable: its address confirmed from the registration email (EmailVerifier), or a first OAuth sign-in creating or taking it over (OAuthUserResolver) - the counterpart of UserAnonymizedEvent, for an app welcoming its new members (a gift, a first item...). Not dispatched for the accounts an administrator creates from the console. #[Exclude] because services.yaml registers all of src/ as services, and an event carrying a user can't be autowired
#[Exclude]
class UserCreatedEvent
{
    public function __construct(
        public readonly UserInterface $user,
    ) {
    }
}
