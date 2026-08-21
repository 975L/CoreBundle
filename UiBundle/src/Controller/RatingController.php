<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\RatingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

// The only uncached thing on an otherwise cached page: the rated page itself is served public (a book's is good for an hour), so the visitor's own vote never travels in its html and is asked for here instead
class RatingController extends AbstractController
{
    public function __construct(
        private readonly RatingService $ratingService,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly ?RateLimiterFactoryInterface $ratingLimiterFactory = null,
    ) {
    }

    #[Route(
        '/rating/{ownerType}/{ownerId}',
        name: 'ui_rating_vote',
        requirements: [
            'ownerType' => '[a-z][a-z0-9_]{0,49}',
            'ownerId' => '\d+',
        ],
        methods: ['POST']
    )]
    public function vote(string $ownerType, int $ownerId, Request $request): Response
    {
        // Same-origin only, and no session token: this route must never make the server open a session, a Set-Cookie being exactly what would poison the shared cache the rated page sits in. A json body already sends any cross-origin caller through a CORS preflight this route does not answer, and the origin check below closes the plain form-post a browser would otherwise deliver without one - which is the whole of what a csrf token would have bought here
        if ('json' !== $request->getContentTypeFormat() || !$this->isSameOrigin($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        // Fails open with no client ip, rather than lumping every such visitor onto one shared bucket - same reading as FormController's
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->ratingLimiterFactory, $clientIp)) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = $request->toArray();
        $value = isset($payload['value']) && is_numeric($payload['value']) ? (int) $payload['value'] : null;
        $token = isset($payload['token']) && \is_string($payload['token']) ? $payload['token'] : null;
        if (null === $value) {
            return new JsonResponse(['error' => 'bad_request'], Response::HTTP_BAD_REQUEST);
        }

        // An anonymous caller that sent no usable token is refused rather than given a key the server made up: a token minted here would count one vote per request, which is no limit at all
        $voter = $this->ratingService->resolveVoter($token);
        if (null === $voter) {
            return new JsonResponse(['error' => 'bad_request'], Response::HTTP_BAD_REQUEST);
        }

        // The scale is the site's own setting and is never read from the body: a forged one would store a score above what the site is rated out of, and the public average would then read "7.3/5"
        $response = new JsonResponse($this->ratingService->vote($ownerType, $ownerId, $value, $voter));
        // Says so explicitly: this answer carries one visitor's own vote and must not be kept by any cache between them and the server
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    // Origin when the browser sent one (it always does on a fetch), the referer's origin otherwise; neither means the request did not come from a page of this site, and is turned down
    private function isSameOrigin(Request $request): bool
    {
        $expected = $request->getSchemeAndHttpHost();

        $origin = $request->headers->get('Origin');
        if (null !== $origin) {
            return $origin === $expected;
        }

        $referer = $request->headers->get('Referer');

        return null !== $referer && str_starts_with($referer, $expected . '/');
    }
}
