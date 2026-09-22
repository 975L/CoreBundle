<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\SameOriginRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

// What stands in for a csrf token on the public json routes: a request from a page of this site, sent as json
class SameOriginRequestTest extends TestCase
{
    public function testAJsonRequestFromThisSiteIsAccepted(): void
    {
        $this->assertTrue(SameOriginRequest::isSameOriginJson($this->request(['HTTP_ORIGIN' => 'http://localhost'])));
    }

    public function testAnotherOriginIsTurnedDown(): void
    {
        $this->assertFalse(SameOriginRequest::isSameOriginJson($this->request(['HTTP_ORIGIN' => 'https://evil.example'])));
    }

    // No Origin header: the referer's origin decides, and a referer merely starting like the host is not one of its pages
    public function testTheRefererStandsInForAMissingOrigin(): void
    {
        $this->assertTrue(SameOriginRequest::isSameOrigin($this->request(['HTTP_REFERER' => 'http://localhost/page'])));
        $this->assertFalse(SameOriginRequest::isSameOrigin($this->request(['HTTP_REFERER' => 'http://localhost.evil.example/page'])));
        $this->assertFalse(SameOriginRequest::isSameOrigin($this->request([])));
    }

    // A plain form post, the one a browser delivers across origins without a preflight, is never taken
    public function testANonJsonRequestIsTurnedDown(): void
    {
        $this->assertFalse(SameOriginRequest::isSameOriginJson($this->request(['HTTP_ORIGIN' => 'http://localhost'], 'application/x-www-form-urlencoded')));
    }

    private function request(array $server, string $contentType = 'application/json'): Request
    {
        return Request::create('/ai-search', 'POST', server: $server + ['CONTENT_TYPE' => $contentType]);
    }
}
