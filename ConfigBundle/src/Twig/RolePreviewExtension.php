<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Security\RolePreview;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

// Hands the Security/RolePreviewBanner component the level being previewed - a function rather than a component class, the components of this ecosystem being anonymous templates (see OAuthLoginExtension)
class RolePreviewExtension
{
    public function __construct(
        private readonly RolePreview $rolePreview,
        private readonly Security $security,
    ) {
    }

    // Null when no preview is on, which is every visitor's case - the user's own roles, getUser() going through no voter
    #[AsTwigFunction('role_preview_level')]
    public function getLevel(): ?string
    {
        $user = $this->security->getUser();

        return null === $user ? null : $this->rolePreview->activeLevel($user->getRoles());
    }
}
