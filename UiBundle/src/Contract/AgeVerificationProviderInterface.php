<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

// One age verification service the gate can open (go.cam ships with this bundle) - auto-tagged, so a site or a bundle adding one only implements it and names its slug in "age-verification-provider". It verifies in a frame over the page and hands the browser a result only it can have written, which AgeVerifier posts back here: the provider reads it, AgeVerifier alone decides what it is worth (nonce, session, cookie). Each provider reads its own configs, the slugs staying its business
#[AutoconfigureTag('c975l.age_verification_provider')]
interface AgeVerificationProviderInterface
{
    // What "age-verification-provider" names it by ("gocam"), and the key of its script in assets/js/age-gate.js
    public function getSlug(): string;

    // The name the visitor is told verifies them ("go.cam")
    public function getName(): string;

    // Whether the site holds the credentials it needs - an unconfigured provider closes no gate at all
    public function isConfigured(): bool;

    // The url its frame opens, carrying the nonce the result has to bring back
    public function getFrameUrl(Request $request, string $nonce): string;

    // The nonce a result carries when it is genuine (signed by the service), recent and a success; null for anything else
    public function readVerifiedNonce(string $payload): ?string;
}
