<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Names what a review is about in a public url without printing its database id there: "book:12" travels signed and encoded, so nobody can write their own token and a walk through /review/book/1..n finds nothing
class ReviewTokenSigner
{
    // Long enough that guessing one is out of reach, short enough that the url stays an url - the payload it protects is two public values, not a secret
    private const int SIGNATURE_LENGTH = 16;

    private const string SEPARATOR = '.';

    public function __construct(
        private readonly string $secret,
    ) {
    }

    // The token naming one thing to review, as the route builds it
    public function sign(string $ownerType, int $ownerId): string
    {
        $payload = self::encode($ownerType . ':' . $ownerId);

        return $payload . self::SEPARATOR . $this->signature($payload);
    }

    /**
     * What the token names, or null when it names nothing this site signed.
     *
     * Null covers everything a caller has to answer the same way - a forged signature, a truncated token, a payload holding no id - the route serving a 404 rather than telling which of the three it was.
     *
     * @return array{ownerType: string, ownerId: int}|null
     */
    public function unsign(string $token): ?array
    {
        $parts = explode(self::SEPARATOR, $token);
        if (2 !== \count($parts)) {
            return null;
        }

        [$payload, $signature] = $parts;

        // Compared in constant time, the signature being the only thing standing between a visitor and an id of their choosing
        if (!hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        $decoded = self::decode($payload);
        if (null === $decoded) {
            return null;
        }

        [$ownerType, $ownerId] = array_pad(explode(':', $decoded, 2), 2, null);

        // The very shapes the route used to state as its own requirements, checked here now that they no longer travel as path parameters
        if (null === $ownerId || 1 !== preg_match('/^[a-z][a-z0-9_]{0,49}$/', (string) $ownerType) || 1 !== preg_match('/^\d+$/', $ownerId)) {
            return null;
        }

        return ['ownerType' => (string) $ownerType, 'ownerId' => (int) $ownerId];
    }

    // Truncated: what it protects is a pair of public values, and a full sha256 would take 43 characters of url to say the same thing
    private function signature(string $payload): string
    {
        return substr(self::encode(hash_hmac('sha256', $payload, $this->secret, true)), 0, self::SIGNATURE_LENGTH);
    }

    // Base64url, so a token drops into a path with nothing to escape
    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
