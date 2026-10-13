<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Service\AppTokenManager;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

// Tells Symfony's own "access_token" authenticator whose token a mobile app sent ("Authorization: Bearer ..."). The application names it as the token_handler of a stateless firewall over "^/app/api/" (see README): a request carrying no token goes through as a visitor, one carrying a token nobody issued is turned away
class AppTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly AppTokenManager $appTokenManager,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $user = $this->appTokenManager->find($accessToken)?->getUser();
        if (null === $user) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        return new UserBadge($user->getUserIdentifier());
    }
}
