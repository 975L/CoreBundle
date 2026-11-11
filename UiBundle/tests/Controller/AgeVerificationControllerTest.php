<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\AgeVerificationController;
use c975L\UiBundle\Service\AgeVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// The route trusts nothing the browser says: the result goes to AgeVerifier, and only its answer leaves a cookie
class AgeVerificationControllerTest extends TestCase
{
    public function testAConfirmedResultLeavesTheCookie(): void
    {
        $response = $this->controller(true)->confirm($this->request(['payload' => 'sealed']));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(AgeVerifier::COOKIE_NAME, $response->headers->getCookies()[0]->getName());
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testARefusedResultLeavesNoCookie(): void
    {
        $response = $this->controller(false)->confirm($this->request(['payload' => 'sealed']));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame([], $response->headers->getCookies());
    }

    public function testAMissingPayloadIsABadRequest(): void
    {
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->controller(true)->confirm($this->request([]))->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->controller(true)->confirm($this->request(['payload' => ['sealed']]))->getStatusCode());
    }

    public function testInvalidJsonIsABadRequest(): void
    {
        $request = $this->request([]);
        $request->initialize([], [], [], [], [], $request->server->all(), '{not json');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', 'http://localhost');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->controller(true)->confirm($request)->getStatusCode());
    }

    public function testAnotherOriginIsTurnedDown(): void
    {
        $request = $this->request(['payload' => 'sealed']);
        $request->headers->set('Origin', 'https://elsewhere.example');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller(true)->confirm($request)->getStatusCode());
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/age-verification', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', 'http://localhost');

        return $request;
    }

    private function controller(bool $confirmed): AgeVerificationController
    {
        $verifier = $this->createStub(AgeVerifier::class);
        $verifier->method('confirm')->willReturn($confirmed);
        $verifier->method('createCookie')->willReturn(Cookie::create(AgeVerifier::COOKIE_NAME, 'value'));

        return new AgeVerificationController($verifier);
    }
}
