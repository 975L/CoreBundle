<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockMoveRowAttrBuilderTest extends TestCase
{
    public function testBuildReturnsEveryAttributeTheSortableReads(): void
    {
        $builder = $this->builder($this->urlGenerator('/management/ui/block/move'));

        $attributes = $builder->build('page', 42);

        $this->assertSame([
            'data-block-collection' => '1',
            'data-block-owner-type' => 'page',
            'data-block-owner-id' => 42,
            'data-block-move-url' => '/management/ui/block/move',
            'data-block-move-csrf-token' => 'a-token',
            'data-block-move-failed-label' => 'Move failed',
        ], $attributes);
    }

    // An entity not saved yet has nothing to drag a block into, so the sortable simply doesn't arm itself
    public function testBuildReturnsNothingForAnUnsavedOwner(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $this->assertSame([], $this->builder($urlGenerator)->build('page', null));
    }

    // A version of this bundle not declaring the route must leave the screen working, not break it
    public function testBuildReturnsNothingWhenTheRouteIsUnknown(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willThrowException(new RouteNotFoundException());

        $this->assertSame([], $this->builder($urlGenerator)->build('page', 42));
    }

    private function urlGenerator(string $url): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($url);

        return $urlGenerator;
    }

    private function builder(UrlGeneratorInterface $urlGenerator): BlockMoveRowAttrBuilder
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken(BlockMoveRowAttrBuilder::ROUTE, 'a-token'));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Move failed');

        return new BlockMoveRowAttrBuilder($urlGenerator, $csrfTokenManager, $translator);
    }
}
