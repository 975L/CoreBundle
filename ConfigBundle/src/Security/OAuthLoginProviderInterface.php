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

// One "sign in with X" button, described. The authorization code flow itself is written once in OAuthLoginClient and never here: what changes from a provider to the next is a handful of endpoints, and the one call that reads back who signed in.
//
// Implement it anywhere - this bundle, another c975L bundle, an application - and it's collected on its own (TaggedInterfacePass, see c975LConfigBundle::build()): no list to edit here, and /connect/{provider} routes it by getKey() the day it appears.
interface OAuthLoginProviderInterface
{
    // Identifies the provider in the url (/connect/google) and names its icon: lowercase, no space
    public function getKey(): string;

    // Displayed on the button ("Continue with Google"), so the provider's own spelling of its name
    public function getName(): string;

    // Where the visitor is sent to consent
    public function getAuthorizationEndpoint(): string;

    // Where the code they come back with is traded for an access token
    public function getTokenEndpoint(): string;

    // Kept to what a login needs and nothing more: the wider the scope, the more likely the provider asks for a review before letting the application out of test mode
    public function getScope(): string;

    // The config slugs holding this site's credentials - declared by whoever ships the provider, in their own configs.json, rather than derived from getKey() here: a provider added by another bundle owns its keys the way SocialBundle owns "social-google-oauth-client-id"
    public function getClientIdSlug(): string;

    public function getClientSecretSlug(): string;

    // Reads back who just signed in. The one place providers genuinely diverge - Google answers a standard userinfo endpoint, Facebook wants its fields listed, Apple says it in the id_token instead of answering anything - so each one does it its own way. Returns null when the answer can't be trusted (no email in it, provider refusing the token), the login being refused rather than guessed
    public function fetchIdentity(string $accessToken): ?OAuthIdentity;
}
