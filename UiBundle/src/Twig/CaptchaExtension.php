<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\CaptchaVerifier;
use Twig\Attribute\AsTwigFunction;

class CaptchaExtension
{
    public function __construct(
        private readonly CaptchaVerifier $captchaVerifier,
    ) {
    }

    // Whether the site's forms send their visitors to Google reCAPTCHA, what the privacy policy has to say
    #[AsTwigFunction('captcha_configured')]
    public function isConfigured(): bool
    {
        return $this->captchaVerifier->isConfigured();
    }
}
