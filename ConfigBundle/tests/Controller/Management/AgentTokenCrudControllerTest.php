<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Controller\Management\AgentTokenCrudController;
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Management\ConfigGuidedProjectProvider;
use c975L\ConfigBundle\Mcp\McpServer;
use c975L\ConfigBundle\Repository\AgentTokenRepository;
use c975L\ConfigBundle\Service\AgentTokenManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class AgentTokenCrudControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private UserInterface $user;

    private Session $session;

    protected function setUp(): void
    {
        $this->user = $this->createStub(UserInterface::class);
    }

    // A new token belongs to the account making it, its secret flashed once with the name escaped and only its hash kept
    public function testANewTokenIsTheAccountsAndShowsItsSecretOnce(): void
    {
        $controller = $this->controller();
        $agentToken = $controller->createEntity(AgentToken::class)->setName('<b>agent-1</b>');
        $this->assertSame($this->user, $agentToken->getUser());

        $controller->persistEntity($this->createStub(EntityManagerInterface::class), $agentToken);
        $flash = $this->session->getFlashBag()->get('success')[0];
        $this->assertStringContainsString('&lt;b&gt;agent-1&lt;/b&gt;', $flash);
        $this->assertMatchesRegularExpression('/[0-9a-f]{64}$/', $flash);
        $this->assertSame(hash('sha256', substr($flash, -64)), $agentToken->getTokenHash());
    }

    // Another account's token is refused, batch delete included, its own revoked
    public function testOnlyTheAccountsOwnTokenIsDeleted(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove');
        $this->controller()->deleteEntity($entityManager, new AgentToken()->setUser($this->user));

        $this->expectException(AccessDeniedException::class);
        $this->controller()->deleteEntity($this->createStub(EntityManagerInterface::class), new AgentToken()->setUser($this->createStub(UserInterface::class)));
    }

    // The row the guided project highlights carries the attribute it looks for, TomSelect hiding the select itself
    public function testTheBundlesRowCarriesTheGuidedHighlight(): void
    {
        $fields = [];
        foreach ($this->controller()->configureFields('new') as $field) {
            $fields[$field->getAsDto()->getProperty()] = $field->getAsDto();
        }
        $this->assertArrayHasKey('data-guided-agent-token-bundles', $fields['bundles']->getFormTypeOption('row_attr'));
        $this->assertStringContainsString("'highlight' => '[data-guided-agent-token-bundles]'", (string) file_get_contents((string) new \ReflectionClass(ConfigGuidedProjectProvider::class)->getFileName()));
    }

    // The screen is titled with its own labels, never the entity's class name
    public function testTheScreenCarriesItsOwnLabels(): void
    {
        $crud = $this->controller()->configureCrud(Crud::new())->getAsDto();
        $singular = $crud->getEntityLabelInSingular();
        $plural = $crud->getEntityLabelInPlural();
        $this->assertInstanceOf(TranslatableMessage::class, $singular);
        $this->assertInstanceOf(TranslatableMessage::class, $plural);
        $this->assertSame('label.agent_token', $singular->getMessage());
        $this->assertSame('label.agent_tokens', $plural->getMessage());
    }

    // The controller signed in as the test's account, its flashes kept in a session
    private function controller(): AgentTokenCrudController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters): string => $parameters['%name%'] . ': ' . $parameters['%token%']);
        $manager = new AgentTokenManager($this->createStub(AgentTokenRepository::class), $this->createStub(EntityManagerInterface::class), new MockClock());
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($this->user, 'main', []));
        [$requestStack, $this->session] = $this->createRequestStackWithSession();

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_CONTRIBUTOR');
        $controller = new AgentTokenCrudController($configService, $manager, new McpServer(), $translator);
        $controller->setContainer($this->createContainer(['security.token_storage' => $tokenStorage, 'request_stack' => $requestStack]));

        return $controller;
    }
}
