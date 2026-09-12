<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\DependencyInjection\Compiler;

use c975L\ConfigBundle\DependencyInjection\Compiler\RolePreviewRoleVoterPass;
use c975L\ConfigBundle\Security\Voter\RolePreviewRoleVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

class RolePreviewRoleVoterPassTest extends TestCase
{
    // The voter as the src/ resource scan registers it, beside the role voters SecurityExtension left in place
    private function createContainer(string ...$roleVoters): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(RolePreviewRoleVoter::class, new Definition(RolePreviewRoleVoter::class));
        foreach ($roleVoters as $roleVoter) {
            $container->setDefinition($roleVoter, new Definition(RoleVoter::class));
        }

        return $container;
    }

    // A site declaring a role_hierarchy keeps the hierarchy voter alone, the one a fixed decoration of the simple voter failed to compile on
    public function testTheHierarchyVoterIsDecoratedOnASiteDeclaringARoleHierarchy(): void
    {
        $container = $this->createContainer('security.access.role_hierarchy_voter');

        new RolePreviewRoleVoterPass()->process($container);

        $this->assertSame('security.access.role_hierarchy_voter', $container->getDefinition(RolePreviewRoleVoter::class)->getDecoratedService()[0]);
    }

    public function testTheSimpleVoterIsDecoratedWithoutARoleHierarchy(): void
    {
        $container = $this->createContainer('security.access.simple_role_voter');

        new RolePreviewRoleVoterPass()->process($container);

        $this->assertSame('security.access.simple_role_voter', $container->getDefinition(RolePreviewRoleVoter::class)->getDecoratedService()[0]);
    }

    // Without SecurityBundle there is nothing to stand in for, and a voter left on its own would ask for a role hierarchy nobody provides
    public function testTheVoterIsRemovedWithoutAnyRoleVoter(): void
    {
        $container = $this->createContainer();

        new RolePreviewRoleVoterPass()->process($container);

        $this->assertFalse($container->hasDefinition(RolePreviewRoleVoter::class));
    }
}
