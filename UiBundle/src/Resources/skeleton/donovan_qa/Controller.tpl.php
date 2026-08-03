<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use <?= $llm_client_full_name ?>;
use <?= $context_builder_full_name ?>;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

// Self-hosted backend for the dashboard assistant; auth is the Bearer token checked below, keeping it stateless
// TODO: add rate limiting once more than one trusted site calls this
#[Route('/api/donovan-qa')]
class <?= $class_name ?>
{
    public function __construct(
        private readonly <?= $llm_client_short_name ?> $llmClient,
        private readonly <?= $context_builder_short_name ?> $contextBuilder,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    #[Route('/ask', name: 'api_donovan_qa_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $token = $this->bearerToken($request);
        if (null === $token || null === $this->resolveSite($token)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $question = trim((string) (\is_array($data) ? ($data['question'] ?? '') : ''));
        if ('' === $question) {
            return new JsonResponse(['error' => 'empty_question'], 400);
        }

        $result = $this->llmClient->ask($question, $this->contextBuilder->context());
        if (null === $result) {
            return new JsonResponse(['error' => 'unavailable'], 503);
        }

        return new JsonResponse([
            'answer' => $result['answer'],
            'sources' => $this->contextBuilder->resolveSources($result['sourceKinds']),
        ]);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }

    // The calling site's own key, or null when the token isn't recognized
    private function resolveSite(string $token): ?string
    {
        $authorizedTokens = (array) $this->configService->get('donovan-qa-authorized-tokens');
        $site = array_search($token, $authorizedTokens, true);

        return false === $site ? null : (string) $site;
    }
}
