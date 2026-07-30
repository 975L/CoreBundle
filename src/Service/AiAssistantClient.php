<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Default AiAssistantClientInterface implementation: forwards the question to whatever plain HTTP endpoint "ui-ai-assistant-dashboard-endpoint" points to, authenticated with a shared Bearer token ("ui-ai-assistant-dashboard-token"). Both are empty by default and this bundle assumes nothing about what's behind that URL - it's on the consuming app to operate (or not operate) such an endpoint
class AiAssistantClient implements AiAssistantClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return true === $this->configService->get('ui-ai-assistant-dashboard-enabled')
            && (bool) $this->configService->get('ui-ai-assistant-dashboard-endpoint')
            && (bool) $this->configService->get('ui-ai-assistant-dashboard-token');
    }

    public function ask(string $question): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $endpoint = $this->configService->get('ui-ai-assistant-dashboard-endpoint');
        $token = $this->configService->get('ui-ai-assistant-dashboard-token');

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'auth_bearer' => $token,
                'json' => ['question' => $question],
                'timeout' => 15,
            ]);

            $data = $response->toArray();

            return [
                'answer' => (string) ($data['answer'] ?? ''),
                'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : [],
            ];
        } catch (ExceptionInterface $e) {
            $this->logger->error('AI assistant request failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
