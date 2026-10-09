<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\FeedController;
use c975L\ConfigBundle\Management\FeedRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FeedControllerTest extends TestCase
{
    private function createController(?string $content): FeedController
    {
        $feedRenderer = $this->createStub(FeedRenderer::class);
        $feedRenderer->method('render')->willReturn($content);

        return new FeedController($feedRenderer);
    }

    public function testTheFeedIsServedAsAtomAndCacheable(): void
    {
        $response = $this->createController('<feed/>')->display(new Request(), 'strip');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/atom+xml; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testNoFeedIsANonExistentPage(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(null)->display(new Request(), 'strip');
    }
}
