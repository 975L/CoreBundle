<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Event;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

// Dispatched once an account is anonymized, by c975l:config:users-cleanup or by its owner (see AccountDeleteController), before the flush: the account is not removed, so a listener on Doctrine's preRemove never runs, and an app unlinking what its users own (shortcuts, ads...) does it here. #[Exclude] because services.yaml registers all of src/ as services, and an event carrying a user can't be autowired
#[Exclude]
class UserAnonymizedEvent
{
    public function __construct(
        public readonly InactivityAwareInterface $user,
        // The address the account held before anonymize() replaced it, for what a bundle keyed by email rather than by user (stock alerts...)
        public readonly ?string $email = null,
    ) {
    }
}
