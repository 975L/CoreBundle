<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Service\FavoriteService;
use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

// Read for the same reason RatingController is: every page carrying a heart is served public and shared between visitors, so what one of them put aside never travels in its html and is asked for here instead
class FavoriteController extends AbstractController
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly ?RateLimiterFactoryInterface $favoriteLimiterFactory = null,
    ) {
    }

    // The page itself holds nothing personal: it is the shell the list is fetched into, so it stays as cacheable as the rest of the site
    #[Route('/favorites', name: 'ui_favorite_page', methods: ['GET'])]
    public function page(): Response
    {
        return $this->render('@c975LUi/favorite/page.html.twig');
    }

    #[Route(
        '/favorite/{ownerType}/{ownerId}',
        name: 'ui_favorite_toggle',
        requirements: [
            'ownerType' => '[a-z][a-z0-9_]{0,49}',
            'ownerId' => '\d+',
        ],
        methods: ['POST']
    )]
    public function toggle(string $ownerType, int $ownerId, Request $request): Response
    {
        $refused = $this->refuse($request);
        if (null !== $refused) {
            return $refused;
        }

        $token = $this->token($request);

        // A visitor who signed in since their last click brings the list their browser was holding with them, before anything is read under either key
        $this->favoriteService->merge($token);

        $holder = $this->favoriteService->resolveHolder($token);
        if (null === $holder) {
            return $this->personal(new JsonResponse(['error' => 'bad_request'], Response::HTTP_BAD_REQUEST));
        }

        return $this->personal(new JsonResponse($this->favoriteService->toggle($ownerType, $ownerId, $holder)));
    }

    /**
     * The list itself, rendered rather than serialized: the cards are the site's own, drawn by the very template every other listing uses, and a wishlist built in javascript would be a second card to maintain.
     *
     * POST and not GET because of the token: it must not reach a url, where it would be written to every access log and kept in the browser's history.
     */
    #[Route('/favorites/list', name: 'ui_favorite_list', methods: ['POST'])]
    public function list(Request $request): Response
    {
        $refused = $this->refuse($request);
        if (null !== $refused) {
            return $refused;
        }

        $token = $this->token($request);
        $this->favoriteService->merge($token);

        $holder = $this->favoriteService->resolveHolder($token);

        // No token and not signed in: an empty list rather than an error, which is exactly what this visitor has
        if (null === $holder) {
            return $this->personal(new JsonResponse(['html' => '', 'keys' => [], 'count' => 0]));
        }

        $favorites = $this->favoriteService->list($holder);

        return $this->personal(new JsonResponse([
            'html' => $this->renderView('@c975LUi/favorite/items.html.twig', ['favorites' => $favorites]),
            // What the hearts of every other page repaint themselves from, so a visitor arriving on a new device is not shown an empty heart over something they already hold
            'keys' => $this->favoriteService->keys($holder),
            'count' => \count($favorites),
        ]));
    }

    // The three checks both routes above share, and the same ones RatingController makes: a json body, a same-origin caller, and a limit
    private function refuse(Request $request): ?Response
    {
        // Same-origin only, and no session token: these routes must never make the server open a session, a Set-Cookie being exactly what would poison the shared cache the page sits in. A json body already sends any cross-origin caller through a CORS preflight they do not answer, and the origin check closes the plain form-post a browser would otherwise deliver without one
        if ('json' !== $request->getContentTypeFormat() || !$this->isSameOrigin($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        // Fails open with no client ip, rather than lumping every such visitor onto one shared bucket - same reading as FormController's
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->favoriteLimiterFactory, $clientIp)) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return null;
    }

    private function token(Request $request): ?string
    {
        $payload = $request->toArray();

        return isset($payload['token']) && \is_string($payload['token']) ? $payload['token'] : null;
    }

    // Says so explicitly: these answers carry one visitor's own list and must not be kept by any cache between them and the server
    private function personal(JsonResponse $response): JsonResponse
    {
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
