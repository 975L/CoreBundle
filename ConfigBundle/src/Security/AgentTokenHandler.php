<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Service\AgentTokenManager;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

// Tells Symfony's "access_token" authenticator whose agent token reached the MCP endpoint, the application naming it as the token_handler of a stateless firewall over "^/api/mcp$" (see README)
class AgentTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly AgentTokenManager $agentTokenManager,
    ) {
    }

    // The account behind the token, refused for an unknown or expired one
    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $user = $this->agentTokenManager->find($accessToken)?->getUser();
        if (null === $user) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        return new UserBadge($user->getUserIdentifier());
    }
}
