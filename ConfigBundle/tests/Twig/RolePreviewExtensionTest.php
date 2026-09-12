<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Twig;

use c975L\ConfigBundle\Security\RolePreview;
use c975L\ConfigBundle\Twig\RolePreviewExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Extension\AttributeExtension;

class RolePreviewExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersTheLevelFunction(): void
    {
        $functions = new AttributeExtension(RolePreviewExtension::class)->getFunctions();

        $this->assertSame(['role_preview_level'], array_map(static fn ($function): string => $function->getName(), $functions));
    }

    // Every visitor's case: no account, so no preview and nothing asked of the session
    public function testNullForAVisitorWithoutAnAccount(): void
    {
        $rolePreview = $this->createMock(RolePreview::class);
        $rolePreview->expects($this->never())->method('activeLevel');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $this->assertNull(new RolePreviewExtension($rolePreview, $security)->getLevel());
    }

    // Checked against the account's real roles, isGranted() already answering for the previewed level
    public function testTheLevelIsCheckedAgainstTheAccountsRealRoles(): void
    {
        $rolePreview = $this->createMock(RolePreview::class);
        $rolePreview->expects($this->once())->method('activeLevel')->with(['ROLE_ADMIN', 'ROLE_USER'])->willReturn('editor');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('owner', null, ['ROLE_ADMIN', 'ROLE_USER']));

        $this->assertSame('editor', new RolePreviewExtension($rolePreview, $security)->getLevel());
    }
}
