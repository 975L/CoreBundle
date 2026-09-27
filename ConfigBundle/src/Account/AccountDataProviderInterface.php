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

// Implement this interface to add what a bundle or the site keeps about a member to the export of their data (/account/export, GDPR rights of access and portability) - the orders of PaymentBundle, the credits of PurchaseCreditsBundle, whatever the site keeps. Picked up by autoconfiguration, check readme for usage
interface AccountDataProviderInterface
{
    // What this member's export holds from here, keyed by what it is ("orders", "credits"...), [] when there is nothing. Scalars, arrays and dates only, dates being written ISO 8601
    /** @return array<string, mixed> */
    public function getAccountData(UserInterface $user): array;
}
