<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Model\OAuthIdentity;

// One "sign in with X" button, described - the flow itself lives in OAuthLoginClient, and an implementation anywhere is collected on its own (TaggedInterfacePass) and routed by getKey()
interface OAuthLoginProviderInterface
{
    // Identifies the provider in the url (/connect/google) and names its icon: lowercase, no space
    public function getKey(): string;

    // Displayed on the button ("Continue with Google"), so the provider's own spelling of its name
    public function getName(): string;

    // Where the visitor is sent to consent
    public function getAuthorizationEndpoint(): string;

    // What the consent url carries beyond the parameters every provider takes - Apple's "response_mode", say
    /** @return array<string, string> */
    public function getAuthorizationParameters(): array;

    // Where the code they come back with is traded for the provider's tokens
    public function getTokenEndpoint(): string;

    // Kept to what a login needs and nothing more: the wider the scope, the more likely the provider asks for a review before letting the application out of test mode
    public function getScope(): string;

    // This site's credentials, read by the provider itself from configs it declares in its own configs.json, null while one is missing: a value pasted as is for most providers, a secret Apple wants signed afresh by the site. Both filled is what displays the button (see OAuthLoginProviderRegistry::enabled())
    public function getClientId(): ?string;

    public function getClientSecret(): ?string;

    // Reads back who just signed in from the token endpoint's whole answer, the one step each provider does its own way, null when the answer can't be trusted
    /** @param array<string, mixed> $token */
    public function fetchIdentity(array $token): ?OAuthIdentity;
}
