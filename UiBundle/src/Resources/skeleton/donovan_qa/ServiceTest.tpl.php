<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace <?= $namespace ?>;

use <?= $entity_full_name ?>;
use <?= $repository_full_name ?>;
use <?= $context_builder_full_name ?>;
use <?= $llm_client_full_name ?>;
use <?= $embedding_client_full_name ?>;
use <?= $service_full_name ?>;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class <?= $class_name ?> extends TestCase
{
    private function createDisabledEmbeddingClient(): <?= $embedding_client_short_name ?>
    {
        $embeddingClient = $this->createStub(<?= $embedding_client_short_name ?>::class);
        $embeddingClient->method('embed')->willReturn(null);

        return $embeddingClient;
    }

    public function testAskReturnsCachedAnswerOnHitWithoutCallingTheLlmOrTheEmbeddingClient(): void
    {
        $existing = (new <?= $entity_short_name ?>())->recordFreshAnswer(
            hash('sha256', 'which block for a gallery?'),
            'which block for a gallery?',
            'Use the collection block.',
            'v1',
            100,
            20,
            [['label' => 'Collection', 'url' => '']],
        );

        $repository = $this->createStub(<?= $repository_short_name ?>::class);
        $repository->method('findOneByQuestionHash')->willReturn($existing);

        $llmClient = $this->createMock(<?= $llm_client_short_name ?>::class);
        $llmClient->expects($this->never())->method('ask');

        $embeddingClient = $this->createMock(<?= $embedding_client_short_name ?>::class);
        $embeddingClient->expects($this->never())->method('embed');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');
        $entityManager->expects($this->never())->method('persist');

        $service = new <?= $class_name ?>(
            $repository,
            $this->createStub(<?= $context_builder_short_name ?>::class),
            $llmClient,
            $embeddingClient,
            $this->createStub(ConfigServiceInterface::class),
            $entityManager,
        );

        $result = $service->ask('Which block for a gallery?');

        $this->assertSame('Use the collection block.', $result['answer']);
        $this->assertSame(1, $existing->getHitCount());
    }

    public function testAskCallsTheLlmAndPersistsANewAnswerOnCacheMiss(): void
    {
        $repository = $this->createStub(<?= $repository_short_name ?>::class);
        $repository->method('findOneByQuestionHash')->willReturn(null);

        $llmClient = $this->createStub(<?= $llm_client_short_name ?>::class);
        $llmClient->method('ask')->willReturn([
            'answer' => 'Use the collection block.',
            'sourceKinds' => ['collection'],
            'inputTokens' => 100,
            'outputTokens' => 20,
        ]);

        $contextBuilder = $this->createStub(<?= $context_builder_short_name ?>::class);
        $contextBuilder->method('resolveSources')->willReturn([['label' => 'Collection', 'url' => '']]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(<?= $entity_short_name ?>::class));
        $entityManager->expects($this->once())->method('flush');

        $service = new <?= $class_name ?>(
            $repository,
            $contextBuilder,
            $llmClient,
            $this->createDisabledEmbeddingClient(),
            $this->createStub(ConfigServiceInterface::class),
            $entityManager,
        );

        $result = $service->ask('Which block for a gallery?');

        $this->assertSame('Use the collection block.', $result['answer']);
        $this->assertSame([['label' => 'Collection', 'url' => '']], $result['sources']);
    }

    public function testAskReturnsNullWhenTheLlmIsUnavailable(): void
    {
        $repository = $this->createStub(<?= $repository_short_name ?>::class);
        $repository->method('findOneByQuestionHash')->willReturn(null);

        $llmClient = $this->createStub(<?= $llm_client_short_name ?>::class);
        $llmClient->method('ask')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new <?= $class_name ?>(
            $repository,
            $this->createStub(<?= $context_builder_short_name ?>::class),
            $llmClient,
            $this->createDisabledEmbeddingClient(),
            $this->createStub(ConfigServiceInterface::class),
            $entityManager,
        );

        $this->assertNull($service->ask('Which block for a gallery?'));
    }

    public function testAskReusesASemanticMatchWithoutCallingTheLlm(): void
    {
        $repository = $this->createStub(<?= $repository_short_name ?>::class);
        $repository->method('findOneByQuestionHash')->willReturn(null);

        $matched = (new <?= $entity_short_name ?>())->recordFreshAnswer(
            hash('sha256', 'how do i add a page?'),
            'how do i add a page?',
            'Menu Management -> Pages, Create Page button.',
            'v1',
            100,
            20,
            [['label' => 'Pages', 'url' => '']],
        );

        $embeddingClient = $this->createStub(<?= $embedding_client_short_name ?>::class);
        $embeddingClient->method('embed')->willReturn([0.1, 0.2, 0.3]);

        $repository->method('findBestSemanticMatch')->willReturn($matched);

        $llmClient = $this->createMock(<?= $llm_client_short_name ?>::class);
        $llmClient->expects($this->never())->method('ask');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(<?= $entity_short_name ?>::class));
        $entityManager->expects($this->once())->method('flush');

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['donovan-qa-semantic-cache-threshold', 0.9],
        ]);

        $service = new <?= $class_name ?>(
            $repository,
            $this->createStub(<?= $context_builder_short_name ?>::class),
            $llmClient,
            $embeddingClient,
            $configService,
            $entityManager,
        );

        $result = $service->ask('How do I create a page?');

        $this->assertSame('Menu Management -> Pages, Create Page button.', $result['answer']);
    }

    public function testAskCallsTheLlmWhenNoSemanticMatchIsCloseEnough(): void
    {
        $repository = $this->createStub(<?= $repository_short_name ?>::class);
        $repository->method('findOneByQuestionHash')->willReturn(null);
        $repository->method('findBestSemanticMatch')->willReturn(null);

        $embeddingClient = $this->createStub(<?= $embedding_client_short_name ?>::class);
        $embeddingClient->method('embed')->willReturn([0.1, 0.2, 0.3]);

        $llmClient = $this->createMock(<?= $llm_client_short_name ?>::class);
        $llmClient->expects($this->once())->method('ask')->willReturn([
            'answer' => 'Fresh answer.',
            'sourceKinds' => [],
            'inputTokens' => 100,
            'outputTokens' => 20,
        ]);

        $contextBuilder = $this->createStub(<?= $context_builder_short_name ?>::class);
        $contextBuilder->method('resolveSources')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new <?= $class_name ?>(
            $repository,
            $contextBuilder,
            $llmClient,
            $embeddingClient,
            $this->createStub(ConfigServiceInterface::class),
            $entityManager,
        );

        $result = $service->ask('Something unrelated?');

        $this->assertSame('Fresh answer.', $result['answer']);
    }
}
