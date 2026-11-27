<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Contract;

use Symfony\Component\Security\Core\User\UserInterface;

// Implement on the application's "register" FormActionInterface when its sign-up asks what Google or Apple cannot answer (a date of birth, the terms of use): a first sign-in through a provider then stops on a page asking those fields of the "register" Form before any account exists (see OAuthUserResolver). A site whose action does not implement it, or names no field, creates the account straight away
interface OAuthSignUpInterface
{
    // The names of the "register" Form's fields asked before the account is created, empty for none
    /** @return list<string> */
    public function getOAuthFieldNames(): array;

    // Fills the account about to be created from what was answered, or returns where to send the visitor instead, nothing being created then
    /** @param array<string, mixed> $submittedData */
    public function prepareOAuthUser(UserInterface $user, array $submittedData): ?string;
}
