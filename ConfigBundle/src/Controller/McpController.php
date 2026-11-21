<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Mcp\McpServer;
use c975L\ConfigBundle\Service\AgentTokenManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// The site's MCP endpoint, closed until "mcp-enabled" is checked, answering an agent token as a Bearer with the rights of its account
class McpController
{
    public const string PATH = '/api/mcp';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AgentTokenManager $agentTokenManager,
        private readonly McpServer $mcpServer,
        private readonly Security $security,
    ) {
    }

    // One MCP request handed to the server for the token it carries, refused without a valid one
    #[Route(self::PATH, name: 'config_mcp', methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function __invoke(Request $request): Response
    {
        if (!(bool) $this->configService->get('mcp-enabled')) {
            throw new NotFoundHttpException();
        }
        $agentToken = $this->agentTokenManager->fromHeader($request->headers->get('Authorization'));
        if (null === $agentToken) {
            return new JsonResponse(['error' => 'Unknown agent token.'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
        }

        // Without the "mcp" firewall of the README the tools would run with no account, every is_granted answering for a visitor and no write logged
        if ($this->security->getUser()?->getUserIdentifier() !== $agentToken->getUser()?->getUserIdentifier()) {
            return new JsonResponse(['error' => 'The agent token\'s account is not signed in: declare the "mcp" firewall (see ConfigBundle\'s README).'], Response::HTTP_FORBIDDEN);
        }

        return $this->mcpServer->handle($request, $agentToken);
    }
}
