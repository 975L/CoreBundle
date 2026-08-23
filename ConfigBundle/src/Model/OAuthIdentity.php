<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Model;

// What a provider tells about whoever just signed in, reduced to the two things the login needs - every provider translating its own vocabulary into this, so OAuthUserResolver never learns that Google says "email_verified" where another says something else.
//
// $emailVerified is the whole security of linking accounts by email alone (no per-provider column, see OAuthUserResolver): a provider that doesn't guarantee the address belongs to the visitor must report false, and the login is refused rather than handed an account.
readonly class OAuthIdentity
{
    public function __construct(
        public string $email,
        public bool $emailVerified,
    ) {
    }
}
