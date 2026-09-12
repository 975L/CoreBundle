<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security\Voter;

use c975L\ConfigBundle\Security\RolePreview;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

// Takes the place of Symfony's own role voter, the only one answering ROLE_* attributes: every isGranted(), #[IsGranted], EasyAdmin permission and access_control rule then sees the previewed level's roles (see RolePreview). Which of Symfony's two role voters it stands in for is decided by RolePreviewRoleVoterPass, and the site's role_hierarchy - empty unless one is declared - is still applied to the previewed roles
class RolePreviewRoleVoter extends RoleVoter
{
    public function __construct(
        private readonly RolePreview $rolePreview,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function extractRoles(TokenInterface $token): array
    {
        return $this->roleHierarchy->getReachableRoleNames($this->rolePreview->effectiveRoles($token));
    }
}
