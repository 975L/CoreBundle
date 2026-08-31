<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use c975L\UiBundle\Controller\Management\AiAssistantController;
use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\AiUsageTracker;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use c975L\UiBundle\Service\ContentTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AiAssistantControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(
        ?AiAssistantClientInterface $aiAssistantClient = null,
        ?AiRephraseClient $aiRephraseClient = null,
        bool $granted = true,
        bool $csrfValid = true,
        array $translatableLocales = [],
    ): AiAssistantController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('getTranslatableLocales')->willReturn($translatableLocales);

        $controller = new AiAssistantController(
            $aiAssistantClient ?? $this->createStub(AiAssistantClientInterface::class),
            $aiRephraseClient ?? $this->createStub(AiRephraseClient::class),
            $this->createStub(AiUsageTracker::class),
            $configService,
            $this->createStub(ConfigRepository::class),
            $this->createStub(ConfigEditUrlResolver::class),
            $contentTranslator,
        );
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker($granted),
            'security.csrf.token_manager' => $this->createCsrfTokenManager($csrfValid),
        ]));

        return $controller;
    }

    public function testAskDeniesAccessWhenNotSuperAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(granted: false)->ask(new Request());
    }

    public function testAskReturnsInvalidCsrfWhenTokenIsInvalid(): void
    {
        $response = $this->createController(csrfValid: false)->ask(new Request());

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame(['error' => 'invalid_csrf'], json_decode((string) $response->getContent(), true));
    }

    public function testAskReturnsEmptyQuestionWhenQuestionIsBlank(): void
    {
        $response = $this->createController()->ask(new Request());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'empty_question'], json_decode((string) $response->getContent(), true));
    }

    public function testAskReturnsUnavailableWhenClientReturnsNull(): void
    {
        $client = $this->createStub(AiAssistantClientInterface::class);
        $client->method('ask')->willReturn(null);

        $response = $this->createController($client)->ask(new Request([], ['question' => 'Which block for a hero banner?']));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['error' => 'unavailable'], json_decode((string) $response->getContent(), true));
    }

    public function testAskReturnsClientResult(): void
    {
        $client = $this->createStub(AiAssistantClientInterface::class);
        $client->method('ask')->willReturn(['answer' => 'Use hero.', 'sources' => []]);

        $response = $this->createController($client)->ask(new Request([], ['question' => 'Which block for a hero banner?']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['answer' => 'Use hero.', 'sources' => []], json_decode((string) $response->getContent(), true));
    }

    public function testRephraseDeniesAccessWhenBelowSiteRoleAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(granted: false)->rephrase(new Request());
    }

    public function testRephraseReturnsInvalidCsrfWhenTokenIsInvalid(): void
    {
        $response = $this->createController(csrfValid: false)->rephrase(new Request());

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame(['error' => 'invalid_csrf'], json_decode((string) $response->getContent(), true));
    }

    public function testRephraseReturnsEmptyTextWhenTextIsBlank(): void
    {
        $response = $this->createController()->rephrase(new Request());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'empty_text'], json_decode((string) $response->getContent(), true));
    }

    public function testRephraseReturnsUnavailableWhenClientReturnsNull(): void
    {
        $client = $this->createStub(AiRephraseClient::class);
        $client->method('rephrase')->willReturn(null);

        $response = $this->createController(null, $client)->rephrase(new Request([], ['text' => 'Hello there']));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['error' => 'unavailable'], json_decode((string) $response->getContent(), true));
    }

    // A target language turns the same endpoint into a translation, which is how the Trix editor's own select reaches it
    public function testRephraseTranslatesWhenATargetLanguageIsAsked(): void
    {
        $client = $this->createMock(AiRephraseClient::class);
        $client->expects($this->once())->method('translate')->with('Hello there', 'es')->willReturn('Hola');
        $client->expects($this->never())->method('rephrase');

        $response = $this->createController(null, $client, translatableLocales: ['es'])
            ->rephrase(new Request([], ['text' => 'Hello there', 'locale' => 'es']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['text' => 'Hola'], json_decode((string) $response->getContent(), true));
    }

    // The locale comes from a form the browser posts: a language the site does not declare is refused rather than paid for
    public function testRephraseRefusesALanguageTheSiteDoesNotDeclare(): void
    {
        $client = $this->createMock(AiRephraseClient::class);
        $client->expects($this->never())->method('translate');

        $response = $this->createController(null, $client, translatableLocales: ['es'])
            ->rephrase(new Request([], ['text' => 'Hello there', 'locale' => 'de']));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'unknown_locale'], json_decode((string) $response->getContent(), true));
    }

    public function testRephraseReturnsRephrasedText(): void
    {
        $client = $this->createStub(AiRephraseClient::class);
        $client->method('rephrase')->willReturn('Hello there, kindly.');

        $response = $this->createController(null, $client)->rephrase(new Request([], ['text' => 'Hello there']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['text' => 'Hello there, kindly.'], json_decode((string) $response->getContent(), true));
    }

    // A slug outside the closed blocking list must never crash missingSlugs()
    private function invokeMissingSlugs(AiAssistantController $controller): array
    {
        return new \ReflectionMethod($controller, 'missingSlugs')->invoke($controller);
    }

    private function invokeConfigLinks(AiAssistantController $controller): array
    {
        return new \ReflectionMethod($controller, 'configLinks')->invoke($controller);
    }

    private function createConfig(string $slug, int $id): Config
    {
        $config = new Config()->setSlug($slug);
        new \ReflectionProperty($config, 'id')->setValue($config, $id);

        return $config;
    }

    // Regression guard: configLinks() must resolve every slug in one batched lookup, not one query each
    public function testConfigLinksResolvesEverySlugInOneQuery(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $configRepository->expects($this->once())
            ->method('findBy')
            ->with($this->callback(
                static fn (array $criteria): bool => isset($criteria['slug']) && in_array('ui-ai-assistant-dashboard-enabled', $criteria['slug'], true)
            ))
            ->willReturn([$this->createConfig('ui-ai-assistant-dashboard-enabled', 42)]);

        $urlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('setEntityId')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/config/edit');

        $controller = new AiAssistantController(
            $this->createStub(AiAssistantClientInterface::class),
            $this->createStub(AiRephraseClient::class),
            $this->createStub(AiUsageTracker::class),
            $this->createStub(ConfigServiceInterface::class),
            $configRepository,
            new ConfigEditUrlResolver($urlGenerator),
            $this->createStub(ContentTranslator::class),
        );

        $links = $this->invokeConfigLinks($controller);

        $this->assertCount(7, $links);
        $this->assertSame('/management/config/edit', $links['ui-ai-assistant-dashboard-enabled']);
    }

    public function testMissingSlugsOmitsAlreadyConfiguredValues(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => match ($slug) {
            'ui-ai-assistant-dashboard-enabled' => true,
            'ui-ai-assistant-dashboard-endpoint' => 'https://example.test/ask',
            'ui-ai-assistant-dashboard-token' => 'token',
            'ui-ai-assistant-rephrase-provider' => 'anthropic',
            'ui-ai-assistant-rephrase-api-key' => 'key',
            default => null,
        });

        $controller = new AiAssistantController(
            $this->createStub(AiAssistantClientInterface::class),
            $this->createStub(AiRephraseClient::class),
            $this->createStub(AiUsageTracker::class),
            $configService,
            $this->createStub(ConfigRepository::class),
            $this->createStub(ConfigEditUrlResolver::class),
            $this->createStub(ContentTranslator::class),
        );

        // "anthropic" doesn't need base-uri/model, and every other slug is filled in above
        $this->assertSame([], $this->invokeMissingSlugs($controller));
    }

    public function testMissingSlugsRequiresBaseUriAndModelOnlyForEuria(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => match ($slug) {
            'ui-ai-assistant-dashboard-enabled' => true,
            'ui-ai-assistant-dashboard-endpoint' => 'https://example.test/ask',
            'ui-ai-assistant-dashboard-token' => 'token',
            'ui-ai-assistant-rephrase-provider' => 'euria',
            'ui-ai-assistant-rephrase-api-key' => 'key',
            default => null,
        });

        $controller = new AiAssistantController(
            $this->createStub(AiAssistantClientInterface::class),
            $this->createStub(AiRephraseClient::class),
            $this->createStub(AiUsageTracker::class),
            $configService,
            $this->createStub(ConfigRepository::class),
            $this->createStub(ConfigEditUrlResolver::class),
            $this->createStub(ContentTranslator::class),
        );

        $this->assertSame(
            ['ui-ai-assistant-rephrase-base-uri', 'ui-ai-assistant-rephrase-model'],
            $this->invokeMissingSlugs($controller)
        );
    }
}
