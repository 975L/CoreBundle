<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Account;

use c975L\ConfigBundle\Contract\UserInterface;

// Implement this interface to add a section to the member's own page (/account), below the profile ConfigBundle draws itself - the orders of PaymentBundle, the credits of PurchaseCreditsBundle, whatever the site keeps for its members. Picked up by autoconfiguration, check readme for usage
interface AccountSectionProviderInterface
{
    // The sections shown to this member, [] when there is nothing for them: 'title' a translation key read in 'translation_domain', 'template' rendered inside a card with 'context', and 'position' ordering them, the lowest first (0 when left out)
    /** @return list<array{title: string, translation_domain: string, template: string, context?: array<string, mixed>, position?: int}> */
    public function getAccountSections(UserInterface $user): array;
}
