<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// reCAPTCHA v3, verified against Google's siteverify endpoint - replaces karser/karser-recaptcha3-bundle, whose only part this ecosystem ever used was this one call (the rest was a vendored copy of google/recaptcha with six interchangeable transports) while three compiler passes/type extensions existed solely to feed it ConfigBundle's values. See CaptchaType/Validator\Constraints\Captcha
class CaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    // The very value config/configs.json seeds "recaptcha3-score-threshold" with, restated here for the two cases that never read a row: an app whose configs were never loaded, and a field emptied at the back office
    // It was Google's own 0.5 and karser's before it, which no site running this bundle has ever been scored on - a loaded config has always answered 0.05 instead - so the constant only ever named a threshold nobody used, and named it ten times stricter than the one they did
    private const DEFAULT_SCORE_THRESHOLD = 0.05;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
    ) {
    }

    // True only when both keys are set: a site key alone would render a widget no one can verify, a secret key alone a check with no token to check
    public function isEnabled(): bool
    {
        return null !== $this->getSiteKey() && null !== $this->getSecretKey();
    }

    public function getSiteKey(): ?string
    {
        return $this->readConfig('recaptcha3-site-key');
    }

    // Below this, Google considers the submission likely automated. 0 is a deliberate, valid value (accept everything), hence the null/'' check rather than a truthy one
    public function getScoreThreshold(): float
    {
        $threshold = $this->readConfig('recaptcha3-score-threshold');

        return null === $threshold ? self::DEFAULT_SCORE_THRESHOLD : (float) $threshold;
    }

    // Fails closed, as karser did: an unverifiable token (Google unreachable, malformed answer, score too low) is rejected rather than waved through
    public function verify(?string $token): bool
    {
        $secretKey = $this->getSecretKey();
        if (null === $secretKey || null === $token || '' === $token) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => array_filter([
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
                ]),
            ]);
            $result = $response->toArray(false);
        } catch (ExceptionInterface | \JsonException) {
            return false;
        }

        if (true !== ($result['success'] ?? false)) {
            return false;
        }

        // No score at all means this token came from a v2 key, not v3 - the threshold can't be applied, so the success flag alone decides
        if (!isset($result['score'])) {
            return true;
        }

        return (float) $result['score'] >= $this->getScoreThreshold();
    }

    private function getSecretKey(): ?string
    {
        return $this->readConfig('recaptcha3-secret-key');
    }

    // An unseeded config key reads as null, an emptied one as '' - neither is a value
    private function readConfig(string $key): ?string
    {
        if (!$this->configService->hasParameter($key)) {
            return null;
        }

        $value = $this->configService->get($key);

        return null === $value || '' === $value ? null : (string) $value;
    }
}
