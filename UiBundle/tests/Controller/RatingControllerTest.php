<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\RatingController;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\RatingService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

// The route takes no csrf token, so what stands in its place is checked here: a json body (which sends a cross-origin caller through a preflight nothing answers) and an origin that is this site's own. Neither may make the server open a session - a Set-Cookie would be kept by the shared cache the rated page sits in
#[AllowMockObjectsWithoutExpectations]
class RatingControllerTest extends TestCase
{
    private const string TOKEN = '0123456789abcdef0123456789abcdef';

    public function testAVoteIsRecordedAndAnsweredWithTheNewTally(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('resolveVoter')->willReturn(self::TOKEN);
        $service->expects($this->once())
            ->method('vote')
            ->with('book', 42, 4, self::TOKEN)
            ->willReturn(['value' => 4, 'average' => 4.2, 'count' => 37])
        ;

        // A scale sent along is ignored: the site's own setting decides what a vote is worth, and vote() takes none
        $response = $this->controller($service)->vote('book', 42, $this->request(['value' => 4, 'token' => self::TOKEN, 'scale' => 10]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['value' => 4, 'average' => 4.2, 'count' => 37], json_decode((string) $response->getContent(), true));
    }

    // One visitor's own vote must never be kept by anything between them and the server, the page it was cast from being public for an hour
    public function testTheAnswerIsNeverCached(): void
    {
        $response = $this->controller()->vote('book', 42, $this->request(['value' => 4, 'token' => self::TOKEN]));

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testAVoteFromAnotherOriginIsTurnedDown(): void
    {
        $request = $this->request(['value' => 4, 'token' => self::TOKEN]);
        $request->headers->set('Origin', 'https://elsewhere.example');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->vote('book', 42, $request)->getStatusCode());
    }

    // A form post carries no json content type, and is exactly what a browser would send cross-origin without asking anyone first
    public function testAVoteThatIsNotJsonIsTurnedDown(): void
    {
        $request = $this->request(['value' => 4, 'token' => self::TOKEN]);
        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->vote('book', 42, $request)->getStatusCode());
    }

    // Neither header means the call did not come from a page of this site
    public function testAVoteWithNeitherOriginNorRefererIsTurnedDown(): void
    {
        $request = $this->request(['value' => 4, 'token' => self::TOKEN]);
        $request->headers->remove('Origin');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->vote('book', 42, $request)->getStatusCode());
    }

    public function testARefererOfThisSiteStandsInForAMissingOrigin(): void
    {
        $request = $this->request(['value' => 4, 'token' => self::TOKEN]);
        $request->headers->remove('Origin');
        $request->headers->set('Referer', 'http://localhost/livre/le-doudou');

        $this->assertSame(Response::HTTP_OK, $this->controller()->vote('book', 42, $request)->getStatusCode());
    }

    // A host this one only starts with is another host: "http://localhost.evil.example" must not pass for "http://localhost"
    public function testARefererOnALookalikeHostIsTurnedDown(): void
    {
        $request = $this->request(['value' => 4, 'token' => self::TOKEN]);
        $request->headers->remove('Origin');
        $request->headers->set('Referer', 'http://localhost.evil.example/livre/le-doudou');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller()->vote('book', 42, $request)->getStatusCode());
    }

    public function testAVoteWithoutAValueIsRefused(): void
    {
        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->controller()->vote('book', 42, $this->request(['token' => self::TOKEN]))->getStatusCode()
        );
    }

    // No usable token and no account: the vote is refused rather than counted under a key the server made up, which would be one vote per request
    public function testAVoteWithNoUsableVoterIsRefused(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('resolveVoter')->willReturn(null);
        $service->expects($this->never())->method('vote');

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->controller($service)->vote('book', 42, $this->request(['value' => 4]))->getStatusCode()
        );
    }

    public function testAVoterOverTheLimitIsTurnedDownWithoutTheVoteBeingRecorded(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('resolveVoter')->willReturn(self::TOKEN);
        $service->expects($this->never())->method('vote');

        $response = $this->controller($service, accepted: false)->vote('book', 42, $this->request(['value' => 4, 'token' => self::TOKEN]));

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/rating/book/42', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', 'http://localhost');

        return $request;
    }

    private function controller(?RatingService $service = null, bool $accepted = true): RatingController
    {
        if (null === $service) {
            $service = $this->createMock(RatingService::class);
            $service->method('resolveVoter')->willReturn(self::TOKEN);
            $service->method('vote')->willReturn(['value' => 4, 'average' => 4.0, 'count' => 1]);
        }

        $limit = $this->createMock(RateLimit::class);
        $limit->method('isAccepted')->willReturn($accepted);
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limit);
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return new RatingController($service, new RateLimiterGuard(), $factory);
    }
}
