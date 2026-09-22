<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Registry\AiSearchCardRegistry;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\SameOriginRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

// The site search's one route, asked by the "ai_search" block. Every question may cost the site's own key, so it is guarded twice: per caller ("ui_ai_search"), and for the whole site ("ui_ai_search_site"), the ceiling a crowd of addresses can't walk through
class AiSearchController extends AbstractController
{
    public function __construct(
        private readonly AiSiteSearch $aiSiteSearch,
        private readonly AiSearchCardRegistry $cardRegistry,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly SiteLocales $siteLocales,
        private readonly ?RateLimiterFactoryInterface $aiSearchLimiterFactory = null,
        private readonly ?RateLimiterFactoryInterface $aiSearchSiteLimiterFactory = null,
    ) {
    }

    #[Route('/ai-search', name: 'ui_ai_search_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        // Same-origin json only, and no session: the block sits on pages served cached and shared, see SameOriginRequest
        if (!SameOriginRequest::isSameOriginJson($request)) {
            return $this->answer(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->aiSiteSearch->isEnabled()) {
            return $this->answer(['error' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = $request->toArray();
        $question = $payload['question'] ?? null;
        if (!\is_string($question) || mb_strlen(trim($question)) < AiSiteSearch::MIN_LENGTH || mb_strlen($question) > AiSiteSearch::MAX_LENGTH) {
            return $this->answer(['error' => 'bad_request'], Response::HTTP_BAD_REQUEST);
        }

        // Fails open with no client ip, same reading as RatingController's
        $clientIp = $request->getClientIp();
        $callerAccepted = null === $clientIp || $this->rateLimiterGuard->isAcceptedForIp($this->aiSearchLimiterFactory, $clientIp);
        if (!$callerAccepted || !$this->rateLimiterGuard->isAccepted($this->aiSearchSiteLimiterFactory, 'site')) {
            return $this->answer(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $result = $this->aiSiteSearch->ask($question, $this->pageLocale($payload));
        if (null === $result) {
            return $this->answer(['error' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->answer($this->withCards($result));
    }

    // The sources a bundle draws as a card of its own (a product, with its price and its basket button) taken out of the plain links and handed over as html. Rendered here and never stored with the answer, so what a card says is what holds when it is read
    /**
     * @param array{answer: string, sources: list<array{url: string, title: string}>, found: bool} $result
     *
     * @return array{answer: string, sources: list<array{url: string, title: string}>, found: bool, cards: string}
     */
    private function withCards(array $result): array
    {
        $cards = $this->cardRegistry->render(array_column($result['sources'], 'url'));
        $result['sources'] = array_values(array_filter($result['sources'], fn (array $source): bool => !isset($cards[$source['url']])));
        $result['cards'] = implode('', $cards);

        return $result;
    }

    // The locale of the page the question was asked on, sent by the block: the route carries no "_locale", and the session's or the browser's may not be the language the page is read in
    private function pageLocale(array $payload): string
    {
        $locale = $payload['locale'] ?? null;

        return \in_array($locale, $this->siteLocales->all(), true) ? $locale : $this->siteLocales->getDefaultLocale();
    }

    // Never kept by a cache between the visitor and the server: it answers one visitor's own question
    private function answer(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store, private']);
    }
}
