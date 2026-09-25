<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Contract;

// A user whose inactivity is tracked, so c975l:config:users-cleanup can warn then anonymize the accounts left unused (GDPR storage limitation). Optional rather than added to UserInterface: a site whose User doesn't implement it yet keeps working, the command and LastLoginSubscriber simply leaving it alone until it re-scaffolds
interface InactivityAwareInterface extends UserInterface
{
    // The domain of the address an anonymized account is given, reserved by RFC 2606 so no email can ever reach it - also how the command tells those accounts apart without a column of their own
    public const string ANONYMIZED_DOMAIN = 'anonymized.invalid';

    public function getEmail(): ?string;

    // A disabled account (banned, or never confirmed) is anonymized without being warned, since it cannot log in
    public function isEnabled(): bool;

    // Last successful login, set to the creation date for an account that never logged in, so the clock always has a start
    public function getLastLogin(): ?\DateTimeInterface;

    public function setLastLogin(\DateTimeInterface $lastLogin): static;

    // When the "your account will be anonymized" email was sent, null once the user logs in again
    public function getInactivityNoticeSentAt(): ?\DateTimeInterface;

    public function setInactivityNoticeSentAt(?\DateTimeInterface $inactivityNoticeSentAt): static;

    // Replaces every personal data by a neutral value, the row itself staying for what refers to it (payments, invoices, credits kept for the accounting retention). The app's entity knows its own fields, hence here rather than in the command
    public function anonymize(): void;
}
