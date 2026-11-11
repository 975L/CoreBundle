<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Service\AgeVerifier;
use c975L\UiBundle\Service\SameOriginRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Where the age gate posts the result go.cam handed the browser. No csrf token: the nonce the gate drew into the session is one already, the result having to carry it back (see AgeVerifier)
class AgeVerificationController extends AbstractController
{
    public function __construct(
        private readonly AgeVerifier $ageVerifier,
    ) {
    }

    // Marks the visitor verified, and leaves the cookie that keeps them so
    #[Route('/age-verification', name: 'ui_age_verification_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        if (!SameOriginRequest::isSameOriginJson($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray()['payload'] ?? null;
        } catch (JsonException) {
            $payload = null;
        }

        // Every way of failing answers the same bare 400: telling a forged result from a stale one would only help forge the next
        if (!\is_string($payload) || !$this->ageVerifier->confirm($payload)) {
            return new JsonResponse(['error' => 'bad_request'], Response::HTTP_BAD_REQUEST);
        }

        $response = new JsonResponse(['verified' => true]);
        $response->headers->setCookie($this->ageVerifier->createCookie());
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
