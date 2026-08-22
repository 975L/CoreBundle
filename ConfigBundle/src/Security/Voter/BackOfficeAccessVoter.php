<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security\Voter;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// Decides who may enter the back office at all - the floor the dashboard and the screens open to everyone standing on it are gated by, each screen still stating its own bar on top. One attribute rather than a role, because the floor is "holds any of the three bars": no role_hierarchy is shipped (see UserManagementVoter and PrivilegedAccountCounter, both written for that same reason), so an account holding ROLE_ADMIN alone passes no site-role-editor gate on its own, and would be locked out of the very dashboard its role is meant to open. Also usable as an EasyAdmin menu permission, which goes through isGranted() just the same
class BackOfficeAccessVoter extends Voter
{
    public const ACCESS = 'C975L_ACCESS_BACK_OFFICE';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute;
    }

    // Any of the three: the editor bar is the base role of the back office, the admin one sits above it for the site's own settings, and ROLE_SUPER_ADMIN above that - each held outright rather than inherited
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        foreach (['site-role-editor', 'site-role-admin'] as $slug) {
            $role = (string) $this->configService->get($slug);

            if ('' !== $role && $this->security->isGranted($role)) {
                return true;
            }
        }

        return $this->security->isGranted('ROLE_SUPER_ADMIN');
    }
}
