<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Mcp;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Controller\McpController;
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Mcp\McpRateLimitExemption;
use c975L\ConfigBundle\Mcp\McpServer;
use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Repository\AgentTokenRepository;
use c975L\ConfigBundle\Service\AgentTokenManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class McpServerTest extends TestCase
{
    private AgentToken $agentToken;

    protected function setUp(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user-1@example.com');
        $this->agentToken = new AgentToken()->setUser($user)->setName('agent-1')->setTokenHash(hash('sha256', 'token-1'));
    }

    // Closed until the config entry is checked, then refusing a request without a known token
    public function testClosedAndRefused(): void
    {
        $refused = false;
        try {
            $this->send(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'token-1', false);
        } catch (NotFoundHttpException) {
            $refused = true;
        }
        $this->assertTrue($refused);
        $this->assertSame(401, $this->send(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'token-2')->getStatusCode());
    }

    // The endpoint alone is kept out of the front limiter, a path merely starting like it is not
    public function testOnlyTheEndpointIsExemptFromTheRateLimiter(): void
    {
        $this->assertTrue(new McpRateLimitExemption()->isExempt(McpController::PATH));
        $this->assertFalse(new McpRateLimitExemption()->isExempt(McpController::PATH . '-other'));
    }

    // An account past its ceiling is told to wait, whatever token it uses
    public function testAnAccountPastItsCeilingWaits(): void
    {
        $limiter = new RateLimiterFactory(['id' => 'mcp', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $this->send($message, 'token-1', limiter: $limiter);

        $refused = $this->send($message, 'token-1', limiter: $limiter);
        $this->assertSame(429, $refused->getStatusCode());
        $this->assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
    }

    // A deleting tool is kept from a reading token even when it does not say it writes
    public function testADeletingToolIsAWritingOne(): void
    {
        McpServerTestProvider::$deleteWrites = false;
        try {
            $this->assertSame(['catalog_list'], array_column($this->tools(), 'name'));
        } finally {
            McpServerTestProvider::$deleteWrites = true;
        }
    }

    // A valid token whose account no firewall signed in is refused, rather than running the tools as a visitor
    public function testRefusedWithoutTheFirewall(): void
    {
        $this->assertSame(403, $this->send(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'token-1', signedIn: false)->getStatusCode());
    }

    // A reading token lists the reading tools of the bundles it allows, a writing one their writing tools too, marked so
    public function testToolsKeptToTheToken(): void
    {
        $this->assertSame(['catalog_list'], array_column($this->tools(), 'name'));

        $this->agentToken->setWritable(true);
        $tools = array_column($this->tools(), null, 'name');
        $this->assertSame(['catalog_list', 'catalog_delete'], array_keys($tools));
        $this->assertTrue($tools['catalog_list']['annotations']['readOnlyHint']);
        $this->assertTrue($tools['catalog_delete']['annotations']['destructiveHint']);

        $this->agentToken->setBundles(['item']);
        $this->assertSame([], $this->tools());
    }

    // A tool called answers with what its callable returned, the instructions telling the token's reach
    public function testCallAndInstructions(): void
    {
        $initialized = $this->call(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'client-1', 'version' => '1.0']]]);
        $this->assertSame(McpServer::NAME, $initialized['result']['serverInfo']['name']);
        $this->assertSame('This token may only read. Items of the catalog.', $initialized['result']['instructions']);

        $result = $this->call(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'catalog_list', 'arguments' => ['search' => 'item']]])['result'];
        $this->assertSame(['item-1'], json_decode($result['content'][0]['text'], true));
    }

    // The tools a tools/list answers
    /** @return list<array<string, mixed>> */
    private function tools(): array
    {
        return $this->call(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'];
    }

    // The decoded JSON-RPC answer to a message sent with the token
    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function call(array $message): array
    {
        $response = $this->send($message, 'token-1');
        $this->assertSame(200, $response->getStatusCode());
        if ($response instanceof StreamedResponse) {
            ob_start();
            $response->sendContent();
            preg_match_all('/^data: (.+)$/m', (string) ob_get_clean(), $events);

            return json_decode((string) end($events[1]), true, flags: \JSON_THROW_ON_ERROR);
        }

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    // A message sent to the endpoint with the token as a Bearer, the endpoint open or not, the firewall having signed the token's account in or not
    /** @param array<string, mixed> $message */
    private function send(array $message, string $token, bool $enabled = true, bool $signedIn = true, ?RateLimiterFactory $limiter = null): Response
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($signedIn ? $this->agentToken->getUser() : null);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn($enabled);
        $repository = $this->createStub(AgentTokenRepository::class);
        $repository->method('findOneByTokenHash')->willReturnCallback(fn (string $hash): ?AgentToken => $hash === $this->agentToken->getTokenHash() ? $this->agentToken : null);
        $manager = new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock());
        $server = ['HTTP_HOST' => 'site.example', 'HTTPS' => 'on', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];

        return new McpController($config, $manager, new McpServer([new McpServerTestProvider()]), $security, $limiter ?? new RateLimiterFactory(['id' => 'mcp', 'policy' => 'no_limit'], new InMemoryStorage()))(Request::create('https://site.example' . McpController::PATH, 'POST', server: $server, content: json_encode($message, \JSON_THROW_ON_ERROR)));
    }
}

// A bundle offering one reading and one deleting tool
class McpServerTestProvider implements McpToolProviderInterface
{
    // Whether the deleting tool also says it writes
    public static bool $deleteWrites = true;

    public function getMcpKey(): string
    {
        return 'catalog';
    }

    public function getMcpInstructions(): string
    {
        return 'Items of the catalog.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('catalog_list', 'Items', 'Items holding the words.', $this->list(...));
        yield new McpTool('catalog_delete', 'Delete an item', 'Deletes an item.', static fn (int $item): array => ['deleted' => $item], self::$deleteWrites, true);
    }

    // Items holding the words
    /**
     * @param string $search words looked for
     *
     * @return list<string>
     */
    public function list(string $search = ''): array
    {
        return str_contains('item-1', $search) ? ['item-1'] : [];
    }
}
