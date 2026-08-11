<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\StatusController;
use c975L\ConfigBundle\Management\StatusReportBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatusControllerTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private const array REPORT = ['version' => 1, 'site' => 'https://papa-calin.com'];

    private function createController(?string $configuredKey, ?LoggerInterface $logger = null): StatusController
    {
        $statusReportBuilder = $this->createStub(StatusReportBuilder::class);
        $statusReportBuilder->method('build')->willReturn(self::REPORT);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-status-key', $configuredKey]]);

        return new StatusController($statusReportBuilder, $configService, $logger);
    }

    private function createRequest(?string $givenKey): Request
    {
        $request = new Request();

        if (null !== $givenKey) {
            $request->headers->set(StatusController::KEY_HEADER, $givenKey);
        }

        return $request;
    }

    public function testTheReportIsServedToWhoeverHoldsTheKey(): void
    {
        $response = $this->createController(self::KEY)->report($this->createRequest(self::KEY));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::REPORT, json_decode($response->getContent(), true));
    }

    // Copied out of a back office field, a key easily carries a trailing space on one side and not on the other
    public function testSurroundingSpaceDoesNotChangeTheKey(): void
    {
        $response = $this->createController(' ' . self::KEY)->report($this->createRequest(self::KEY . "\n"));

        $this->assertSame(200, $response->getStatusCode());
    }

    // Nothing says which of the three refusals happened, and none of them says the url exists
    public function testAWrongKeyIsRefusedAsANonExistentPage(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(self::KEY)->report($this->createRequest(str_repeat('f', 64)));
    }

    public function testNoKeyAtAllIsRefusedAsANonExistentPage(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(self::KEY)->report($this->createRequest(null));
    }

    // Installing the bundle must never make a site answer a stranger: with nothing configured there is nothing to compare, so every caller is refused - including one presenting an empty key
    public function testASiteWithNoKeyConfiguredAnswersNobody(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(null)->report($this->createRequest(''));
    }

    // A key short enough to be guessed online is worse than no key at all, the route answering as often as it is asked - so it is treated as none, and the site answers nobody rather than answering weakly
    public function testAKeyShorterThanTheMinimumAnswersNobodyEvenToItself(): void
    {
        $shortKey = str_repeat('a', 31);

        $this->expectException(NotFoundHttpException::class);

        $this->createController($shortKey)->report($this->createRequest($shortKey));
    }

    // The caller is told nothing, but the site's own log is where a campaign of attempts becomes visible
    public function testARefusalIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->expectException(NotFoundHttpException::class);

        $this->createController(self::KEY, $logger)->report($this->createRequest(str_repeat('f', 64)));
    }

    // A request carrying no key at all is any scanner walking urls: logging those would bury the attempts that mean something under the noise
    public function testARequestPresentingNoKeyIsNotLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->expectException(NotFoundHttpException::class);

        $this->createController(self::KEY, $logger)->report($this->createRequest(null));
    }

    // The body depends on a header, so a shared cache holding it would serve one caller's report to the next
    public function testTheReportIsKeptOutOfEveryCache(): void
    {
        $response = $this->createController(self::KEY)->report($this->createRequest(self::KEY));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }
}
