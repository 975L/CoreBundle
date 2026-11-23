<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Mcp;

use c975L\ConfigBundle\Entity\AgentToken;
use Composer\InstalledVersions;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Server;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// The site's MCP server over Streamable HTTP, without sessions: the tools of every bundle the agent token allows, the writing ones only for a token that may write
class McpServer
{
    public const string NAME = 'c975l';

    /** @param iterable<McpToolProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    // The answer to one MCP request of an agent
    public function handle(Request $request, AgentToken $agentToken): Response
    {
        $factory = new Psr17Factory();

        // CORS only: the token travels as a Bearer and never as a cookie, so the DNS rebinding check, limited to localhost, would only refuse the site's own host
        $transport = new StreamableHttpTransport(
            new PsrHttpFactory($factory, $factory, $factory, $factory)->createRequest($request),
            $factory,
            $factory,
            $this->logger,
            [new CorsMiddleware()],
        );
        $response = $this->server($agentToken)->run($transport);

        return new HttpFoundationFactory()->createResponse($response, 'text/event-stream' === strtolower($response->getHeaderLine('Content-Type')));
    }

    // The tools the token is given, by bundle
    /** @return list<McpTool> */
    public function tools(AgentToken $agentToken): array
    {
        $tools = [];
        foreach ($this->providers as $provider) {
            if (!$agentToken->allows($provider->getMcpKey())) {
                continue;
            }
            foreach ($provider->getMcpTools() as $tool) {
                if ($agentToken->isWritable() || (!$tool->writes && !$tool->destroys)) {
                    $tools[] = $tool;
                }
            }
        }

        return $tools;
    }

    // Keys of the bundles offering tools
    /** @return list<string> */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->providers as $provider) {
            $keys[] = $provider->getMcpKey();
        }

        return $keys;
    }

    // The server and the token's tools, the reading ones marked so, the deleting ones destructive
    private function server(AgentToken $agentToken): Server
    {
        $builder = Server::builder()
            ->setServerInfo(self::NAME, InstalledVersions::getPrettyVersion('c975l/core-bundle') ?? 'dev', 'The tools of the site, with the rights of the account the agent token belongs to.')
            ->setInstructions($this->instructions($agentToken))
            ->setLogger($this->logger ?? new NullLogger());
        foreach ($this->tools($agentToken) as $tool) {
            $annotations = new ToolAnnotations(readOnlyHint: !$tool->writes && !$tool->destroys, destructiveHint: $tool->destroys, openWorldHint: false);
            $builder->addTool($tool->handler, $tool->name, $tool->title, $tool->description, $annotations);
        }

        return $builder->build();
    }

    // What the bundles allowed tell the agent, the token's reach first
    private function instructions(AgentToken $agentToken): string
    {
        $lines = [$agentToken->isWritable() ? 'This token may read and write.' : 'This token may only read.'];
        foreach ($this->providers as $provider) {
            if ($agentToken->allows($provider->getMcpKey()) && '' !== $provider->getMcpInstructions()) {
                $lines[] = $provider->getMcpInstructions();
            }
        }

        return implode(' ', $lines);
    }
}
