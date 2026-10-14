<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\FavoriteService;
use c975L\UiBundle\Service\PlayService;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\SameOriginRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

// One more time a page of this site played something to its end - a story heard on the listening page. Counted in the total whoever listened, and under its holder when there is one: the account, or the token a browser already holds for its favorites. None is made up for it, a listener being no one who asked to be remembered
class PlayController extends AbstractController
{
    public function __construct(
        private readonly PlayService $playService,
        private readonly FavoriteService $favoriteService,
        private readonly FavoriteItemRegistry $favoriteItemRegistry,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly ?RateLimiterFactoryInterface $favoriteLimiterFactory = null,
    ) {
    }

    #[Route(
        '/play/{ownerType}/{ownerId}',
        name: 'ui_play',
        requirements: [
            'ownerType' => '[a-z][a-z0-9_]{0,49}',
            'ownerId' => '\d+',
        ],
        methods: ['POST']
    )]
    public function play(string $ownerType, int $ownerId, Request $request): Response
    {
        // The checks FavoriteController makes, for the same reasons: a same-origin json call opening no session, and a limit
        if (!SameOriginRequest::isSameOriginJson($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->favoriteLimiterFactory, $clientIp)) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Nothing is counted for a thing nobody on this site can show
        if ([] === $this->favoriteItemRegistry->resolve([$ownerType => [$ownerId]])) {
            throw $this->createNotFoundException();
        }

        try {
            $token = $request->toArray()['token'] ?? null;
        } catch (JsonException) {
            $token = null;
        }

        $this->playService->record($ownerType, $ownerId, $this->favoriteService->resolveHolder(\is_string($token) ? $token : null));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
