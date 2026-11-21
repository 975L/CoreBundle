<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Repository\AgentTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

// Issues the tokens an AI agent presents to the MCP endpoint and finds the one a request carries, an expired one opening nothing
class AgentTokenManager
{
    // A token's last use is only rewritten once it is that old: one write a minute, not one per tool call
    private const string LAST_USE_PRECISION = '-1 minute';

    /** @var array<string, ?AgentToken> */
    private array $found = [];

    public function __construct(
        private readonly AgentTokenRepository $agentTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    // A new secret for the token, its hash kept on it, the secret itself returned to be shown once
    public function issue(AgentToken $agentToken): string
    {
        $token = bin2hex(random_bytes(32));
        $agentToken->setTokenHash(self::hash($token));

        return $token;
    }

    // The token an agent sent, null for an unknown one, one past its day or one of a disabled account, kept for the rest of the request; notes that it served
    public function find(#[\SensitiveParameter] string $token): ?AgentToken
    {
        $hash = self::hash($token);
        if (\array_key_exists($hash, $this->found)) {
            return $this->found[$hash];
        }
        $agentToken = $this->agentTokenRepository->findOneByTokenHash($hash);
        $now = $this->clock->now();
        if (null === $agentToken || !self::opens($agentToken, $now)) {
            return $this->found[$hash] = null;
        }
        if (null === $agentToken->getLastUse() || $agentToken->getLastUse() < $now->modify(self::LAST_USE_PRECISION)) {
            $agentToken->setLastUse($now);
            $this->entityManager->flush();
        }

        return $this->found[$hash] = $agentToken;
    }

    // The token an "Authorization: Bearer" header carries, as find() reads it
    public function fromHeader(?string $authorization): ?AgentToken
    {
        return 1 === preg_match('/^Bearer\s+(\S+)$/', trim((string) $authorization), $matches) ? $this->find($matches[1]) : null;
    }

    // Whether the token still opens: its account there and enabled (a banned one losing its agents too, the stateless firewall having no user checker), its day not past
    private static function opens(AgentToken $agentToken, \DateTimeImmutable $now): bool
    {
        $user = $agentToken->getUser();

        return null !== $user && (!$user instanceof InactivityAwareInterface || $user->isEnabled()) && !$agentToken->isExpired($now);
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
