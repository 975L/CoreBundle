<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\AgeVerification;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AgeVerificationProviderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

// go.cam (double anonymity, Arcom framework), its iframe protocol recoded rather than its SDK vendored, the hosted one shipping with no licence: a request is encrypted with the partner's cipher key and signed with its hmac key, and so is the result coming back - never the "isPayloadValid" go.cam's own script answers in the browser, nor its postback, which carries no signature. Keys from the "Standard implementation" page of the go.cam partner area
class GoCamProvider implements AgeVerificationProviderInterface
{
    private const string FRAME_URL = 'https://go.cam/%s/token/iframeCheck?p=%s&d=%s';

    private const array LANGUAGES = ['en', 'fr', 'de', 'nl', 'it', 'pt', 'es'];

    // Counted from the page drawn, go.cam's frame loading with it: a visitor reading for a while before the click still gets an answer, the single-use nonce being what stops a replay
    private const int RESULT_MAX_AGE = 3600;

    private const int STATE_SUCCESS = 2;

    private const string CIPHER = 'aes-256-cbc';

    private const int HMAC_SIZE = 32;

    // go.cam's numbered keys, which their SDK names KEY_ACCOUNT_DATA, KEY_USER_IP_STR...
    private const int KEY_USER_DATA = 2;

    private const int KEY_IP = 3;

    private const int KEY_USER_AGENT = 6;

    private const int KEY_LINK_BACK = 7;

    private const int KEY_HOSTNAME = 8;

    private const int KEY_PARAMS = 9;

    private const int KEY_VERSION = 10;

    private const int KEY_RESULT = 11;

    private const int VERSION_IFRAME = 2;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getSlug(): string
    {
        return 'gocam';
    }

    public function getName(): string
    {
        return 'go.cam';
    }

    // All three keys set and well-formed
    public function isConfigured(): bool
    {
        return null !== $this->getKeys();
    }

    // The request sealed with the partner's keys, in the visitor's language when go.cam speaks it
    public function getFrameUrl(Request $request, string $nonce): string
    {
        $keys = $this->getKeys();
        if (null === $keys) {
            throw new \LogicException('go.cam is not configured.');
        }

        $data = [
            'requestTime' => $this->clock->now()->getTimestamp(),
            self::KEY_USER_DATA => json_encode(['userId' => 0, 'nonce' => $nonce], \JSON_THROW_ON_ERROR),
            self::KEY_IP => (string) $request->getClientIp(),
            self::KEY_USER_AGENT => (string) $request->headers->get('User-Agent'),
            self::KEY_LINK_BACK => $request->getUri(),
            self::KEY_HOSTNAME => $request->getHost(),
            // No e-mail anywhere, neither as a way to verify nor as the fallback of a failed one: the point is that nobody learns who the visitor is
            self::KEY_PARAMS => [
                'verificationTypeList' => ['selfie', 'scanId', 'nfcId'],
                'verificationTypeDefault' => 'selfie',
                'useEmailAsFallbackVerification' => false,
            ],
            self::KEY_VERSION => self::VERSION_IFRAME,
        ];

        return \sprintf(self::FRAME_URL, $this->getLanguage($request), $keys['partnerId'], rawurlencode($this->seal($data, $keys)));
    }

    // Signed with our key, recent, and go.cam's success state: the nonce it carries, null otherwise
    public function readVerifiedNonce(string $payload): ?string
    {
        $keys = $this->getKeys();
        $data = null === $keys ? null : $this->open($payload, $keys);
        if (null === $data) {
            return null;
        }

        $userData = json_decode(\is_string($data[self::KEY_USER_DATA] ?? null) ? $data[self::KEY_USER_DATA] : '', true);
        $nonce = \is_array($userData) && \is_string($userData['nonce'] ?? null) ? $userData['nonce'] : null;
        $age = abs($this->clock->now()->getTimestamp() - (int) ($data['requestTime'] ?? 0));
        $state = \is_array($data[self::KEY_RESULT] ?? null) ? (int) ($data[self::KEY_RESULT]['stateInt'] ?? 0) : 0;

        return $age <= self::RESULT_MAX_AGE && self::STATE_SUCCESS === $state ? $nonce : null;
    }

    // Encrypts then signs: base64url(hmac(iv . ciphertext) . iv . ciphertext), go.cam's format both ways
    private function seal(array $data, array $keys): string
    {
        $iv = random_bytes(16);
        $ciphertext = (string) openssl_encrypt(json_encode($data, \JSON_THROW_ON_ERROR), self::CIPHER, $keys['cipherKey'], \OPENSSL_RAW_DATA, $iv);
        $buffer = $iv . $ciphertext;

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $buffer, $keys['hmacKey'], true) . $buffer), '+/', '-_'), '=');
    }

    // The signature is checked before anything is decrypted, so a tampered payload is never even read
    private function open(string $payload, array $keys): ?array
    {
        $buffer = base64_decode(strtr($payload, '-_', '+/'), true);
        if (false === $buffer || \strlen($buffer) <= self::HMAC_SIZE + 16) {
            return null;
        }

        $signed = substr($buffer, self::HMAC_SIZE);
        if (!hash_equals(hash_hmac('sha256', $signed, $keys['hmacKey'], true), substr($buffer, 0, self::HMAC_SIZE))) {
            return null;
        }

        $json = openssl_decrypt(substr($signed, 16), self::CIPHER, $keys['cipherKey'], \OPENSSL_RAW_DATA, substr($signed, 0, 16));
        $data = false === $json ? null : json_decode($json, true);

        return \is_array($data) ? $data : null;
    }

    // The visitor's language when go.cam speaks it, English otherwise
    private function getLanguage(Request $request): string
    {
        $language = substr($request->getLocale(), 0, 2);

        return \in_array($language, self::LANGUAGES, true) ? $language : 'en';
    }

    // The three keys, binary, or null when one is missing or not the hex go.cam hands out
    private function getKeys(): ?array
    {
        $partnerId = $this->readConfig('age-verification-gocam-partner-id');
        $cipherKey = $this->readConfig('age-verification-gocam-cipher-key');
        $hmacKey = $this->readConfig('age-verification-gocam-hmac-key');
        if (null === $partnerId || !ctype_digit($partnerId) || !$this->isHex($cipherKey, 64) || !$this->isHex($hmacKey)) {
            return null;
        }

        return ['partnerId' => $partnerId, 'cipherKey' => hex2bin((string) $cipherKey), 'hmacKey' => hex2bin((string) $hmacKey)];
    }

    // Hex of an even length, and of the expected one when given
    private function isHex(?string $value, ?int $length = null): bool
    {
        return null !== $value && ctype_xdigit($value) && 0 === \strlen($value) % 2 && (null === $length || $length === \strlen($value));
    }

    // An unseeded config key reads as null, an emptied one as '' - neither is a value
    private function readConfig(string $key): ?string
    {
        if (!$this->configService->hasParameter($key)) {
            return null;
        }

        $value = $this->configService->get($key);

        return null === $value || '' === $value ? null : trim((string) $value);
    }
}
