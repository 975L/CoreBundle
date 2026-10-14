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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

// The favorites and the plays of the site's mobile app. FavoriteController's routes are closed to it on purpose - they only answer a page of this site, which an app is not - so these do the same work for a caller that is no browser: no cookie to ride, hence nothing for a same-origin check to protect. Held under the very keys the site uses: the token the device keeps, then the account once the app is connected to one (ConfigBundle's AppConnectController), what the device held being handed over on the way (see FavoriteService::merge())
#[Route('/app/api')]
class AppLibraryController extends AbstractController
{
    private const array OWNER_REQUIREMENTS = ['ownerType' => '[a-z][a-z0-9_]{0,49}', 'ownerId' => '\d+'];

    // How many things one handover names at most
    private const int HANDOVER_MAX = 200;

    public function __construct(
        private readonly FavoriteService $favoriteService,
        private readonly PlayService $playService,
        private readonly FavoriteItemRegistry $favoriteItemRegistry,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly ?RateLimiterFactoryInterface $favoriteLimiterFactory = null,
    ) {
    }

    // Everything this holder put aside and played, which is what an app shows on a new phone once connected. An app used before it was connected sends along what it held alone ("favorites" and "plays", under the keys of the answer), for the account to take it in
    #[Route('/library', name: 'ui_app_library', methods: ['POST'])]
    public function library(Request $request): Response
    {
        $holder = $this->holder($request) ?? self::nobody();
        if ($holder instanceof Response) {
            return $holder;
        }

        $body = self::body($request);
        $this->takeIn($holder, $body['favorites'] ?? null, $body['plays'] ?? null);

        return $this->answer($holder);
    }

    // Puts a thing aside or takes it back out, the app saying which: sent twice, the same order changes nothing, unlike the site's own toggle
    #[Route('/favorite/{ownerType}/{ownerId}', name: 'ui_app_favorite', requirements: self::OWNER_REQUIREMENTS, methods: ['POST'])]
    public function favorite(string $ownerType, int $ownerId, Request $request): Response
    {
        $holder = $this->holder($request) ?? self::nobody();
        if ($holder instanceof Response) {
            return $holder;
        }
        $this->denyUnlessKnown($ownerType, $ownerId);

        $wanted = false !== (self::body($request)['favorited'] ?? true);
        if ($wanted !== \in_array($ownerType . ':' . $ownerId, $this->favoriteService->keys($holder), true)) {
            $this->favoriteService->toggle($ownerType, $ownerId, $holder);
        }

        return $this->answer($holder);
    }

    // One more time this thing was played to its end. A listener with no account and no token is counted all the same, in the total alone: an app counts every listen without making up anything that names the phone
    #[Route('/play/{ownerType}/{ownerId}', name: 'ui_app_play', requirements: self::OWNER_REQUIREMENTS, methods: ['POST'])]
    public function play(string $ownerType, int $ownerId, Request $request): Response
    {
        $holder = $this->holder($request);
        if ($holder instanceof Response) {
            return $holder;
        }
        $this->denyUnlessKnown($ownerType, $ownerId);

        $this->playService->record($ownerType, $ownerId, $holder);

        return null === $holder ? new Response(null, Response::HTTP_NO_CONTENT) : $this->answer($holder);
    }

    // The key this caller's things are held under, null for a caller with neither an account nor a token the device made up, or a refusal for too many calls. What the device held is handed over to the account on the way
    private function holder(Request $request): string | Response | null
    {
        // Fails open with no client ip, as FavoriteController does
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->favoriteLimiterFactory, $clientIp)) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $token = self::body($request)['token'] ?? null;
        $token = \is_string($token) ? $token : null;

        $this->favoriteService->merge($token);
        $holder = $this->favoriteService->resolveHolder($token);
        if (null === $holder) {
            return null;
        }

        // Only a token of the shape the favorites accept ever names rows to move
        if (null !== $token && $holder !== $token && 1 === preg_match(FavoriteService::TOKEN_PATTERN, $token)) {
            $this->playService->merge($token, $holder);
        }

        return $holder;
    }

    // Adds what an app held to what its holder already holds: its favorites join the list, and each thing is counted as played at least as many times as the app says. Sent twice, the same handover changes nothing the second time
    private function takeIn(string $holder, mixed $favorites, mixed $plays): void
    {
        $favorites = \is_array($favorites) ? array_unique(array_filter($favorites, \is_string(...))) : [];
        $plays = \is_array($plays) ? array_filter($plays, \is_int(...)) : [];
        if ([] === $favorites && [] === $plays) {
            return;
        }

        $known = $this->known([...$favorites, ...array_keys($plays)]);

        foreach (array_diff(array_intersect($favorites, $known), $this->favoriteService->keys($holder)) as $key) {
            [$ownerType, $ownerId] = explode(':', $key);
            $this->favoriteService->toggle($ownerType, (int) $ownerId, $holder);
        }

        $this->playService->raise($holder, array_intersect_key($plays, array_flip($known)));
    }

    // Among the keys an app names ("book:12"), those of things this site can show
    /**
     * @param array<int|string> $keys
     *
     * @return list<string>
     */
    private function known(array $keys): array
    {
        $ids = [];
        foreach (\array_slice(array_unique($keys), 0, self::HANDOVER_MAX) as $key) {
            if (\is_string($key) && 1 === preg_match('/^([a-z][a-z0-9_]{0,49}):([1-9]\d{0,8})$/', $key, $matches)) {
                $ids[$matches[1]][] = (int) $matches[2];
            }
        }

        return array_map(
            static fn (array $resolved): string => $resolved['ownerType'] . ':' . $resolved['ownerId'],
            $this->favoriteItemRegistry->resolve($ids)
        );
    }

    // A thing nobody on this site can show is not put aside nor counted: the app is talking of something that is gone, or of nothing
    private function denyUnlessKnown(string $ownerType, int $ownerId): void
    {
        if ([] === $this->favoriteItemRegistry->resolve([$ownerType => [$ownerId]])) {
            throw $this->createNotFoundException();
        }
    }

    // What the app repaints itself from, kept by no cache on the way: one holder's own
    private function answer(string $holder): JsonResponse
    {
        $response = new JsonResponse([
            'favorites' => $this->favoriteService->keys($holder),
            'plays' => (object) $this->playService->counts($holder),
            'connected' => null !== $this->getUser(),
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    // Neither an account nor a token: there is no list to answer with
    private static function nobody(): JsonResponse
    {
        return new JsonResponse(['error' => 'bad_request'], Response::HTTP_BAD_REQUEST);
    }

    /** @return array<string, mixed> */
    private static function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            return [];
        }
    }
}
