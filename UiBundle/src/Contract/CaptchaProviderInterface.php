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

// One anti-bot service the public forms can be protected by (reCAPTCHA ships with this bundle) - auto-tagged, so a site or a bundle adding one only implements it and names its slug in "captcha-provider". Its browser script mints a token on submit, which the provider alone can check; CaptchaVerifier decides when it is asked. Each provider reads its own configs, the slugs staying its business
#[AutoconfigureTag('c975l.captcha_provider')]
interface CaptchaProviderInterface
{
    // What "captcha-provider" names it by ("recaptcha"), the key of its script in assets/js/captcha.js and of its paragraph in the privacy policy model
    public function getSlug(): string;

    // The name the privacy policy tells visitors their data goes to ("Google reCAPTCHA")
    public function getName(): string;

    // Whether the site holds the keys it needs - an unconfigured provider protects nothing and is never asked
    public function isConfigured(): bool;

    // The public key its browser script is loaded with
    public function getSiteKey(): ?string;

    // Whether the token the browser minted proves a human, failing closed on anything the service cannot vouch for
    public function verify(string $token, ?string $clientIp): bool;
}
