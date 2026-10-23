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
use c975L\UiBundle\Service\PlayTicket;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\SameOriginRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

// One more time a page of this site played something - a story heard on the listening page. Counted in the total whoever listened, and under its holder when there is one: the account, or the token a browser already holds for its favorites. None is made up for it, a listener being no one who asked to be remembered. Only on a ticket the page took when it started playing, for enough of it to have gone by on the server's clock (see PlayTicket)
class PlayController extends AbstractController
{
    private const array OWNER_REQUIREMENTS = [
        'ownerType' => '[a-z][a-z0-9_]{0,49}',
        'ownerId' => '\d+',
    ];

    public function __construct(
        private readonly PlayService $playService,
        private readonly PlayTicket $playTicket,
        private readonly FavoriteService $favoriteService,
        private readonly FavoriteItemRegistry $favoriteItemRegistry,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly ?RateLimiterFactoryInterface $favoriteLimiterFactory = null,
    ) {
    }

    // A playthrough starts: the ticket the page keeps until it counts, and the part of it to be played by then
    #[Route('/play/{ownerType}/{ownerId}/start', name: 'ui_play_start', requirements: self::OWNER_REQUIREMENTS, methods: ['POST'])]
    public function start(string $ownerType, int $ownerId, Request $request): Response
    {
        $refused = $this->refused($ownerType, $ownerId, $request);
        if (null !== $refused) {
            return $refused;
        }

        return new JsonResponse([
            'ticket' => $this->playTicket->issue($ownerType, $ownerId, $this->holder($request)),
            'ratio' => $this->playTicket->ratio(),
        ]);
    }

    #[Route('/play/{ownerType}/{ownerId}', name: 'ui_play', requirements: self::OWNER_REQUIREMENTS, methods: ['POST'])]
    public function play(string $ownerType, int $ownerId, Request $request): Response
    {
        $refused = $this->refused($ownerType, $ownerId, $request);
        if (null !== $refused) {
            return $refused;
        }

        // A ticket played too little, someone else's or already counted adds nothing, and is told so: the page has nothing to do about it
        $holder = $this->holder($request);
        $ticket = $this->body($request)['ticket'] ?? null;
        if (!\is_string($ticket) || !$this->playTicket->isHeard($ticket, $ownerType, $ownerId, $holder) || !$this->playTicket->spend($ticket)) {
            return new JsonResponse(['error' => 'not_counted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->playService->record($ownerType, $ownerId, $holder);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // The checks FavoriteController makes, for the same reasons: a same-origin json call opening no session, and a limit. Then nothing for a thing nobody on this site can show
    private function refused(string $ownerType, int $ownerId, Request $request): ?Response
    {
        if (!SameOriginRequest::isSameOriginJson($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->favoriteLimiterFactory, $clientIp)) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        if ([] === $this->favoriteItemRegistry->resolve([$ownerType => [$ownerId]])) {
            throw $this->createNotFoundException();
        }

        return null;
    }

    private function holder(Request $request): ?string
    {
        $token = $this->body($request)['token'] ?? null;

        return $this->favoriteService->resolveHolder(\is_string($token) ? $token : null);
    }

    /** @return array<mixed> */
    private function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            return [];
        }
    }
}
