<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AppToken;
use c975L\ConfigBundle\Repository\AppTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

// Connects a mobile app to a member's account the way a native app should (RFC 8252): the member signs in on the site, in their browser, by whatever means the site offers; the app is then handed a one-time code through its own url scheme and trades it, with the secret only it holds (PKCE), for the token it will send from then on. The app never sees a password, and another app registering the same scheme gets a code it cannot trade
class AppTokenManager
{
    // What a PKCE challenge looks like: the base64url of a sha256, unpadded
    public const string CHALLENGE_PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    // How long the app has to trade its code: the time of a redirection, not of a coffee
    private const string CODE_LIFETIME = '+2 minutes';

    // A token's last use is only rewritten once it is that old: one write a day, not one per request
    private const string LAST_USE_PRECISION = '-1 day';

    public function __construct(
        private readonly AppTokenRepository $appTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The url schemes this site hands codes to ("papacalin"), from the "app-connect-schemes" entry: none on a site with no app, which then offers no connection at all
    /** @return list<string> */
    public function schemes(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $scheme): string => strtolower(trim($scheme)),
            explode(',', (string) $this->configService->get('app-connect-schemes'))
        )));
    }

    // Whether a code may be sent to that address: one of the site's own apps, and nothing else - a code sent anywhere else is an account given away
    public function isAllowedRedirect(string $redirect): bool
    {
        $scheme = parse_url($redirect, \PHP_URL_SCHEME);

        return \is_string($scheme) && \in_array(strtolower($scheme), $this->schemes(), true);
    }

    // The one-time code for a member who agreed to connect their app, bound to the challenge the app sent
    public function createCode(UserInterface $user, string $challenge, ?string $label): string
    {
        $now = new \DateTimeImmutable();
        $this->appTokenRepository->deleteExpiredCodes($now);

        $code = bin2hex(random_bytes(32));
        $appToken = new AppToken()
            ->setUser($user)
            ->setCodeHash(self::hash($code))
            ->setChallenge($challenge)
            ->setCodeExpiration($now->modify(self::CODE_LIFETIME))
            ->setLabel(null === $label ? null : mb_substr(trim($label), 0, 100))
        ;
        $this->entityManager->persist($appToken);
        $this->entityManager->flush();

        return $code;
    }

    // The token a code is traded for, null when the code is unknown, already traded, too old, or brought by someone who does not hold the verifier
    public function exchange(string $code, string $verifier): ?string
    {
        $appToken = $this->appTokenRepository->findOneByCodeHash(self::hash($code));
        if (null === $appToken) {
            return null;
        }

        // Whatever comes next, the code has been played: a second try with it finds nothing
        $challenge = (string) $appToken->getChallenge();
        $expired = null === $appToken->getCodeExpiration() || $appToken->getCodeExpiration() < new \DateTimeImmutable();
        $appToken->setCodeHash(null)->setChallenge(null)->setCodeExpiration(null);

        if ($expired || !hash_equals($challenge, self::challengeOf($verifier))) {
            $this->entityManager->remove($appToken);
            $this->entityManager->flush();

            return null;
        }

        $token = bin2hex(random_bytes(32));
        $appToken->setTokenHash(self::hash($token));
        $this->entityManager->flush();

        return $token;
    }

    // The row behind a token the app sent, null for one that was never issued or was revoked. Notes that it served, a day at most behind
    public function find(string $token): ?AppToken
    {
        $appToken = $this->appTokenRepository->findOneByTokenHash(self::hash($token));
        if (null === $appToken) {
            return null;
        }

        $now = new \DateTimeImmutable();
        if (null === $appToken->getLastUse() || $appToken->getLastUse() < $now->modify(self::LAST_USE_PRECISION)) {
            $appToken->setLastUse($now);
            $this->entityManager->flush();
        }

        return $appToken;
    }

    // The app signs out: its token opens nothing any more
    public function revoke(string $token): void
    {
        $appToken = $this->appTokenRepository->findOneByTokenHash(self::hash($token));
        if (null !== $appToken) {
            $this->entityManager->remove($appToken);
            $this->entityManager->flush();
        }
    }

    // The challenge a verifier answers: its sha256, base64url, unpadded (the "S256" method of RFC 7636)
    public static function challengeOf(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
