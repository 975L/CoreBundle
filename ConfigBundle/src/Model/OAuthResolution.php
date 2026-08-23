<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Model;

use c975L\ConfigBundle\Contract\UserInterface;

// What OAuthUserResolver decided about an identity: the account to log in, or none at all - with the reason, the refusals having nothing to say to each other.
//
// $passwordReset says the account existed but had never confirmed its email, so its password was replaced before being handed over (see OAuthUserResolver) - the controller tells the visitor, whose former password stopped working.
readonly class OAuthResolution
{
    // The provider wouldn't vouch for the address, so it never reached an account
    public const string REASON_EMAIL_NOT_VERIFIED = 'email_not_verified';

    // The address is fine, but this site isn't taking new accounts: an existing one would have been logged in
    public const string REASON_REGISTRATION_CLOSED = 'registration_closed';

    // The account exists and confirmed its address once, and an admin has disabled it since: a provider's door is a door all the same
    public const string REASON_ACCOUNT_DISABLED = 'account_disabled';

    public function __construct(
        public ?UserInterface $user,
        public bool $passwordReset = false,
        public ?string $reason = null,
    ) {
    }

    // Refusal reads better than "null user" at the call sites, which only ever ask that
    public static function refused(string $reason): self
    {
        return new self(null, false, $reason);
    }
}
