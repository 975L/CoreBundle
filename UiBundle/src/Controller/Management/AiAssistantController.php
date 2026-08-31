<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\AiUsageTracker;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use c975L\UiBundle\Service\ContentTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Two independent endpoints, each with its own config and role: a site can enable one without the other
class AiAssistantController extends AbstractController
{
    // EasyAdmin prefixes these with the Dashboard's own route name
    public const INDEX_ROUTE = 'management_ui_ai_assistant_index';
    public const ASK_ROUTE = 'management_ui_ai_assistant_ask';
    public const REPHRASE_ROUTE = 'management_ui_ai_assistant_rephrase';

    // Every config slug the setup guide below links to individually
    private const array LINKED_SLUGS = [
        'ui-ai-assistant-dashboard-enabled',
        'ui-ai-assistant-dashboard-endpoint',
        'ui-ai-assistant-dashboard-token',
        'ui-ai-assistant-rephrase-provider',
        'ui-ai-assistant-rephrase-api-key',
        'ui-ai-assistant-rephrase-base-uri',
        'ui-ai-assistant-rephrase-model',
    ];

    public function __construct(
        private readonly AiAssistantClientInterface $aiAssistantClient,
        private readonly AiRephraseClient $aiRephraseClient,
        private readonly AiUsageTracker $aiUsageTracker,
        private readonly ConfigServiceInterface $configService,
        private readonly ConfigRepository $configRepository,
        private readonly ConfigEditUrlResolver $configEditUrlResolver,
        private readonly ContentTranslator $contentTranslator,
    ) {
    }

    // Shown even when nothing is configured, the page itself being the setup landing spot
    #[AdminRoute(path: '/ui/ai-assistant', name: 'ui_ai_assistant_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->render('@c975LUi/management/ai_assistant.html.twig', [
            'assistantName' => 'Donovan',
            'enabled' => $this->aiAssistantClient->isEnabled(),
            'rephraseEnabled' => $this->aiRephraseClient->isEnabled(),
            'rephraseUsage' => $this->aiUsageTracker->getCurrentMonth(),
            'configLinks' => $this->configLinks(),
            'missingSlugs' => $this->missingSlugs(),
        ]);
    }

    // {slug: edit url} for LINKED_SLUGS, batched in one query rather than one per slug
    private function configLinks(): array
    {
        $configsBySlug = [];
        foreach ($this->configRepository->findBy(['slug' => self::LINKED_SLUGS]) as $config) {
            $configsBySlug[$config->getSlug()] = $config;
        }

        $links = [];
        foreach (self::LINKED_SLUGS as $slug) {
            $links[$slug] = $this->configEditUrlResolver->resolve($configsBySlug[$slug] ?? null);
        }

        return $links;
    }

    // Per-slug rather than all-or-nothing, so a filled-in step stops being prompted; "-base-uri"/"-model" only block euria
    private function missingSlugs(): array
    {
        $blockingSlugs = self::LINKED_SLUGS;
        if ('euria' !== $this->configService->get('ui-ai-assistant-rephrase-provider')) {
            $blockingSlugs = array_diff($blockingSlugs, ['ui-ai-assistant-rephrase-base-uri', 'ui-ai-assistant-rephrase-model']);
        }

        return array_values(array_filter($blockingSlugs, function (string $slug): bool {
            $value = $this->configService->get($slug);

            return 'ui-ai-assistant-dashboard-enabled' === $slug ? true !== $value : !$value;
        }));
    }

    #[AdminRoute(
        path: '/ui/ai-assistant/ask',
        name: 'ui_ai_assistant_ask',
        options: ['methods' => ['POST']]
    )]
    public function ask(Request $request): JsonResponse
    {
        // Stricter than rephrase(): this spends against a shared backend, not the site's own budget
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid(self::ASK_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'invalid_csrf'], 419);
        }

        $question = trim((string) $request->request->get('question', ''));
        if ('' === $question) {
            return new JsonResponse(['error' => 'empty_question'], 400);
        }

        $result = $this->aiAssistantClient->ask($question);

        return null === $result
            ? new JsonResponse(['error' => 'unavailable'], 503)
            : new JsonResponse($result);
    }

    #[AdminRoute(
        path: '/ui/ai-assistant/rephrase',
        name: 'ui_ai_assistant_rephrase',
        options: ['methods' => ['POST']]
    )]
    public function rephrase(Request $request): JsonResponse
    {
        // The site's own key and budget, so the lower bar is enough, still above a plain editor
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::REPHRASE_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'invalid_csrf'], 419);
        }

        $text = trim((string) $request->request->get('text', ''));
        if ('' === $text) {
            return new JsonResponse(['error' => 'empty_text'], 400);
        }

        // A target language turns the same request into a translation - same key, same budget, only the prompt differs - and is checked against what the site declares, a request parameter being written by whoever wants
        $locale = (string) $request->request->get('locale', '');
        if ('' !== $locale) {
            if (!\in_array($locale, $this->contentTranslator->getTranslatableLocales(), true)) {
                return new JsonResponse(['error' => 'unknown_locale'], 400);
            }

            $translated = $this->aiRephraseClient->translate($text, $locale);

            return null === $translated
                ? new JsonResponse(['error' => 'unavailable'], 503)
                : new JsonResponse(['text' => $translated]);
        }

        // Not validated here: rephrase() falls back to "neutral"/"same" for anything outside its closed lists
        $style = (string) $request->request->get('style', 'neutral');
        $length = (string) $request->request->get('length', 'same');

        $result = $this->aiRephraseClient->rephrase($text, $style, $length);

        return null === $result
            ? new JsonResponse(['error' => 'unavailable'], 503)
            : new JsonResponse(['text' => $result]);
    }
}
