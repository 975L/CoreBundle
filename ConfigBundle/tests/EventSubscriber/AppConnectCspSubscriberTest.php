<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\EventSubscriber\AppConnectCspSubscriber;
use c975L\ConfigBundle\Repository\AppTokenRepository;
use c975L\ConfigBundle\Service\AppTokenManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class AppConnectCspSubscriberTest extends TestCase
{
    private const string POLICY = "default-src 'self'; form-action 'self' checkout.stripe.com; frame-ancestors 'none'";

    // Behind NelmioSecurityBundle's listener and the WebProfiler's, which both write the policy
    public function testSubscribesBehindThePolicyWriters(): void
    {
        $listener = new \ReflectionMethod(AppConnectCspSubscriber::class, 'onKernelResponse')->getAttributes(AsEventListener::class)[0]->newInstance();
        $this->assertSame([KernelEvents::RESPONSE, -1000], [$listener->event, $listener->priority]);
    }

    // The connect page lets its form redirect to the app, on every policy header, the rest of the policy untouched
    public function testAddsTheAppSchemesOnTheConnectPage(): void
    {
        $response = $this->response();

        $this->subscriber('papacalin, guilde')->onKernelResponse($this->event('config_app_connect', $response));

        $expected = "default-src 'self'; form-action 'self' checkout.stripe.com papacalin: guilde:; frame-ancestors 'none'";
        $this->assertSame($expected, $response->headers->get('Content-Security-Policy'));
        $this->assertSame($expected, $response->headers->get('X-Content-Security-Policy'));
    }

    // No other page of the site may send the browser to an app
    public function testLeavesOtherPagesAlone(): void
    {
        $response = $this->response();

        $this->subscriber('papacalin')->onKernelResponse($this->event('app_home', $response));

        $this->assertSame(self::POLICY, $response->headers->get('Content-Security-Policy'));
    }

    // A site with no app has nothing to add
    public function testLeavesThePolicyAloneWithoutScheme(): void
    {
        $response = $this->response();

        $this->subscriber('')->onKernelResponse($this->event('config_app_connect', $response));

        $this->assertSame(self::POLICY, $response->headers->get('Content-Security-Policy'));
    }

    // 'none' cannot stand next to another source: it is dropped rather than the whole policy thrown away by the browser
    public function testDropsNone(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "form-action 'none'");

        $this->subscriber('papacalin')->onKernelResponse($this->event('config_app_connect', $response));

        $this->assertSame('form-action papacalin:', $response->headers->get('Content-Security-Policy'));
    }

    // A policy without "form-action" restricts no form, so it is not given one
    public function testLeavesAPolicyWithoutFormAction(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "default-src 'self'");

        $this->subscriber('papacalin')->onKernelResponse($this->event('config_app_connect', $response));

        $this->assertSame("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    private function response(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', self::POLICY);
        $response->headers->set('X-Content-Security-Policy', self::POLICY);

        return $response;
    }

    private function subscriber(string $schemes): AppConnectCspSubscriber
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($schemes);

        return new AppConnectCspSubscriber(new AppTokenManager(
            $this->createStub(AppTokenRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $configService,
        ));
    }

    private function event(string $route, Response $response): ResponseEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new ResponseEvent($this->createStub(KernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
