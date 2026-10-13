<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\AppTokenManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// A member's account opened to the site's own mobile app (see AppTokenManager for the why of each step). One page in the browser, where the member is signed in and agrees; then three calls the app makes by itself under "/app/api/", the stateless firewall the application declares for it (see README). A site naming no url scheme in "app-connect-schemes" has no app, and answers 404 to all of it
class AppConnectController extends AbstractController
{
    private const string CSRF_ID = 'app_connect';

    public function __construct(
        private readonly AppTokenManager $appTokenManager,
    ) {
    }

    // The page the app opens in the browser: asks the member, signed in by whatever means the site offers, whether this app may act for them, then sends the app its one-time code
    #[Route('/app/connect', name: 'config_app_connect', methods: ['GET', 'POST'])]
    public function connect(Request $request): Response
    {
        $redirect = (string) $request->query->get('redirect');
        $challenge = (string) $request->query->get('challenge');

        // Checked before anything is said or asked: an address that is not one of the site's apps gets no page to borrow the site's name with
        if (!$this->appTokenManager->isAllowedRedirect($redirect) || 1 !== preg_match(AppTokenManager::CHALLENGE_PATTERN, $challenge)) {
            throw $this->createNotFoundException();
        }

        // Sent to the login form by the firewall's entry point, and brought back here once signed in
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw $this->createAccessDeniedException();
        }

        $state = (string) $request->query->get('state');

        if (!$request->isMethod('POST')) {
            return $this->render('@c975LConfig/app/connect.html.twig', [
                'cancelUrl' => self::back($redirect, ['error' => 'denied', 'state' => $state]),
            ]);
        }

        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $label = $request->query->get('label');
        $code = $this->appTokenManager->createCode($user, $challenge, \is_string($label) ? $label : null);

        return new RedirectResponse(self::back($redirect, ['code' => $code, 'state' => $state]));
    }

    // The app trades its code, and the verifier only it holds, for its token
    #[Route('/app/api/token', name: 'config_app_token', methods: ['POST'])]
    public function token(Request $request): Response
    {
        $this->denyUnlessOffered();

        try {
            $body = $request->toArray();
        } catch (JsonException) {
            $body = [];
        }

        $code = $body['code'] ?? null;
        $verifier = $body['verifier'] ?? null;
        $token = \is_string($code) && \is_string($verifier) ? $this->appTokenManager->exchange($code, $verifier) : null;

        // One answer for every way a code can be wrong: which one it was is nobody's business
        if (null === $token) {
            return new JsonResponse(['error' => 'invalid_grant'], Response::HTTP_BAD_REQUEST);
        }

        return self::private(new JsonResponse(['token' => $token]));
    }

    // The app signs out: the token it sent opens nothing any more
    #[Route('/app/api/token', name: 'config_app_token_revoke', methods: ['DELETE'])]
    public function revoke(Request $request): Response
    {
        $this->denyUnlessOffered();

        if (1 === preg_match('/^Bearer\s+(\S+)$/i', (string) $request->headers->get('Authorization'), $matches)) {
            $this->appTokenManager->revoke($matches[1]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // Whose account the app is connected to, for it to say so
    #[Route('/app/api/me', name: 'config_app_me', methods: ['GET'])]
    public function me(): Response
    {
        $this->denyUnlessOffered();

        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return self::private(new JsonResponse(['identifier' => $user->getUserIdentifier()]));
    }

    private function denyUnlessOffered(): void
    {
        if ([] === $this->appTokenManager->schemes()) {
            throw $this->createNotFoundException();
        }
    }

    // The app's own address with what it is told, added to whatever query it already carried
    /** @param array<string, string> $parameters */
    private static function back(string $redirect, array $parameters): string
    {
        return $redirect . (str_contains($redirect, '?') ? '&' : '?') . http_build_query(array_filter($parameters, static fn (string $value): bool => '' !== $value));
    }

    // Never kept by anything on the way: a token or an address is one member's alone
    private static function private(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
