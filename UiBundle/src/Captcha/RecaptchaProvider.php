<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Captcha;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\CaptchaProviderInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Google reCAPTCHA v3, verified against its siteverify endpoint - replaces karser/karser-recaptcha3-bundle, whose only part this ecosystem ever used was this one call. Keys from the reCAPTCHA admin console
class RecaptchaProvider implements CaptchaProviderInterface
{
    private const string VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    // The very value config/configs.json seeds "captcha-recaptcha-score-threshold" with, restated for an app whose configs were never loaded and a field emptied at the back office
    private const float DEFAULT_SCORE_THRESHOLD = 0.05;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getSlug(): string
    {
        return 'recaptcha';
    }

    public function getName(): string
    {
        return 'Google reCAPTCHA';
    }

    // Both keys set: a site key alone would render a widget no one can verify, a secret key alone a check with no token to check
    public function isConfigured(): bool
    {
        return null !== $this->getSiteKey() && null !== $this->getSecretKey();
    }

    public function getSiteKey(): ?string
    {
        return $this->readConfig('captcha-recaptcha-site-key');
    }

    // Below this, Google considers the submission likely automated. 0 is a deliberate, valid value (accept everything), hence the null check rather than a truthy one
    public function getScoreThreshold(): float
    {
        $threshold = $this->readConfig('captcha-recaptcha-score-threshold');

        return null === $threshold ? self::DEFAULT_SCORE_THRESHOLD : (float) $threshold;
    }

    // Fails closed: Google unreachable, a malformed answer or a score too low rejects the token rather than waving it through
    public function verify(string $token, ?string $clientIp): bool
    {
        $secretKey = $this->getSecretKey();
        if (null === $secretKey || '' === $token) {
            return false;
        }

        try {
            $result = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => array_filter(['secret' => $secretKey, 'response' => $token, 'remoteip' => $clientIp]),
            ])->toArray(false);
        } catch (ExceptionInterface | \JsonException) {
            return false;
        }

        if (true !== ($result['success'] ?? false)) {
            return false;
        }

        // No score at all means this token came from a v2 key, not v3 - the threshold can't be applied, so the success flag alone decides
        return !isset($result['score']) || (float) $result['score'] >= $this->getScoreThreshold();
    }

    private function getSecretKey(): ?string
    {
        return $this->readConfig('captcha-recaptcha-secret-key');
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
