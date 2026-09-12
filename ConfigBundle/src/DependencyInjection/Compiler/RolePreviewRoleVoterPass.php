<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\DependencyInjection\Compiler;

use c975L\ConfigBundle\Security\Voter\RolePreviewRoleVoter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Decorates whichever of Symfony's two role voters the security configuration kept: SecurityExtension removes the simple one when a role_hierarchy is declared and the hierarchy one otherwise, so a fixed #[AsDecorator] target failed the compilation of every site on the other side
class RolePreviewRoleVoterPass implements CompilerPassInterface
{
    private const array ROLE_VOTERS = ['security.access.role_hierarchy_voter', 'security.access.simple_role_voter'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(RolePreviewRoleVoter::class)) {
            return;
        }

        foreach (self::ROLE_VOTERS as $roleVoter) {
            if ($container->hasDefinition($roleVoter)) {
                $container->getDefinition(RolePreviewRoleVoter::class)->setDecoratedService($roleVoter);

                return;
            }
        }

        // No SecurityBundle, no role voter to stand in for - and nothing to autowire its role hierarchy from
        $container->removeDefinition(RolePreviewRoleVoter::class);
    }
}
