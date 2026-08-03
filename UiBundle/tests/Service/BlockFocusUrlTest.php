<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockFocusUrl;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class BlockFocusUrlTest extends TestCase
{
    public function testBuildTargetsTheOwnersEditScreen(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('unsetAll')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setController')->with('App\\Controller\\PageCrudController')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setEntityId')->with(42)->willReturnSelf();
        $urlGenerator->expects($this->never())->method('set');
        $urlGenerator->method('generateUrl')->willReturn('/management?crudAction=edit');

        $url = BlockFocusUrl::build($urlGenerator, 'App\\Controller\\PageCrudController', 42);

        $this->assertSame('/management?crudAction=edit', $url);
    }

    // A block given alongside its owner jumps straight to that block's own row
    public function testBuildAddsTheFocusBlockParameterForAGivenBlock(): void
    {
        $block = $this->block(7);

        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('setEntityId')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('set')->with('focusBlock', 7)->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management?focusBlock=7');

        $url = BlockFocusUrl::build($urlGenerator, 'App\\Controller\\PageCrudController', 42, $block);

        $this->assertSame('/management?focusBlock=7', $url);
    }

    // A Block whose id is only ever set by Doctrine
    private function block(int $id): Block
    {
        $block = new Block();
        $reflection = new \ReflectionProperty(Block::class, 'id');
        $reflection->setValue($block, $id);

        return $block;
    }
}
