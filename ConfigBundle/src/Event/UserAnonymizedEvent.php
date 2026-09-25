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

// Dispatched by c975l:config:users-cleanup once an inactive account is anonymized, before the flush: the account is not removed, so a listener on Doctrine's preRemove never runs, and an app unlinking what its users own (shortcuts, ads...) does it here. #[Exclude] because services.yaml registers all of src/ as services, and an event carrying a user can't be autowired
#[Exclude]
class UserAnonymizedEvent
{
    public function __construct(
        public readonly InactivityAwareInterface $user,
    ) {
    }
}
