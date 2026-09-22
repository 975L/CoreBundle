<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\AiSearchAnswer;
use c975L\UiBundle\Repository\AiSearchAnswerRepository;
use c975L\UiBundle\Repository\AiSearchChunkRepository;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\AiSiteSearchClient;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// The model is called only with passages in hand, never twice for the same question against the same index, and no link it could write ever reaches the visitor
#[AllowMockObjectsWithoutExpectations]
class AiSiteSearchTest extends TestCase
{
    private const array EXCERPTS = [
        ['url' => 'https://site.example/horaires', 'title' => 'Horaires', 'content' => 'Ouvert du lundi au vendredi.'],
        ['url' => 'https://site.example/tarifs', 'title' => 'Tarifs', 'content' => 'Vidange : 80 euros.'],
    ];

    public function testAnswersFromThePassagesAndKeepsOnlyTheSourcesOfTheIndex(): void
    {
        $client = $this->client();
        $client->expects($this->once())->method('answer')
            ->willReturn('```json {"answer": "Du lundi au vendredi.", "sources": [1, 7, "https://evil.example"]} ```');

        $result = $this->search($client)->ask('  Quels   horaires ? ', 'fr');

        $this->assertSame('Du lundi au vendredi.', $result['answer']);
        $this->assertSame([['url' => 'https://site.example/horaires', 'title' => 'Horaires']], $result['sources']);
        $this->assertTrue($result['found']);
    }

    public function testTheSameQuestionAgainstTheSameIndexIsServedWithoutCallingTheModel(): void
    {
        $recorded = new AiSearchAnswer('hash', 'Quels horaires ?', 'fr');
        $recorded->recordAnswer('Du lundi au vendredi.', [], false, 'v1');

        $client = $this->client();
        $client->expects($this->never())->method('answer');

        $result = $this->search($client, recorded: $recorded)->ask('Quels horaires ?', 'fr');

        $this->assertSame('Du lundi au vendredi.', $result['answer']);
        $this->assertSame(2, $recorded->getHitCount());
    }

    public function testAnAnswerRecordedAgainstAnOlderIndexIsAskedAgain(): void
    {
        $recorded = new AiSearchAnswer('hash', 'Quels horaires ?', 'fr');
        $recorded->recordAnswer('Ancienne réponse', [], false, 'v0');

        $client = $this->client();
        $client->expects($this->once())->method('answer')->willReturn('{"answer": "Nouvelle réponse", "sources": [2]}');

        $result = $this->search($client, recorded: $recorded)->ask('Quels horaires ?', 'fr');

        $this->assertSame('Nouvelle réponse', $result['answer']);
        $this->assertSame('v1', $recorded->getIndexVersion());
    }

    // Nothing close in the index: answered "not found" for free, and still recorded - it is what the site lacks
    public function testAQuestionTheIndexHasNothingCloseToNeverReachesTheModel(): void
    {
        $client = $this->client();
        $client->expects($this->never())->method('answer');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->callback(fn (AiSearchAnswer $answer): bool => !$answer->isFound()));

        $result = $this->search($client, [], entityManager: $entityManager)->ask('Avez-vous des chats ?', 'fr');

        $this->assertSame(['answer' => '', 'sources' => [], 'found' => false], $result);
    }

    public function testAProviderFailureIsNotRecorded(): void
    {
        $client = $this->client();
        $client->method('answer')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->assertNull($this->search($client, entityManager: $entityManager)->ask('Quels horaires ?', 'fr'));
    }

    // A reply that isn't the JSON asked for is still shown, its markup stripped, with the passages' pages as its sources
    public function testAReplyThatIsNotJsonIsKeptAsPlainText(): void
    {
        $client = $this->client();
        $client->method('answer')->willReturn('<b>Du lundi</b> au vendredi.');

        $result = $this->search($client)->ask('Quels horaires ?', 'fr');

        $this->assertSame('Du lundi au vendredi.', $result['answer']);
        $this->assertCount(2, $result['sources']);
    }

    // Two visitors asking the same question at once: the second insert loses on the unique hash, and that visitor still gets the answer
    public function testTheSameQuestionRecordedMeanwhileStillAnswersTheVisitor(): void
    {
        $client = $this->client();
        $client->method('answer')->willReturn('{"answer": "Du lundi au vendredi.", "sources": [1]}');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())->method('resetManager');

        $result = $this->search($client, entityManager: $entityManager, registry: $registry)->ask('Quels horaires ?', 'fr');

        $this->assertSame('Du lundi au vendredi.', $result['answer']);
    }

    // An empty or non-positive retention falls back to the default, the same reading the privacy policy prints
    public function testTheRetentionFallsBackToTheDefault(): void
    {
        $this->assertSame(30, AiSiteSearch::retentionDays('30'));
        $this->assertSame(AiSiteSearch::DEFAULT_RETENTION_DAYS, AiSiteSearch::retentionDays(null));
        $this->assertSame(AiSiteSearch::DEFAULT_RETENTION_DAYS, AiSiteSearch::retentionDays(''));
        $this->assertSame(AiSiteSearch::DEFAULT_RETENTION_DAYS, AiSiteSearch::retentionDays('0'));
        $this->assertSame(AiSiteSearch::DEFAULT_RETENTION_DAYS, AiSiteSearch::retentionDays(-5));
    }

    public function testNothingIsAskedWhileTheIndexIsEmpty(): void
    {
        $client = $this->client();
        $client->expects($this->never())->method('answer');

        $this->assertNull($this->search($client, version: null)->ask('Quels horaires ?', 'fr'));
    }

    private function client(): AiSiteSearchClient
    {
        $client = $this->createMock(AiSiteSearchClient::class);
        $client->method('isEnabled')->willReturn(true);

        return $client;
    }

    private function search(
        AiSiteSearchClient $client,
        array $excerpts = self::EXCERPTS,
        ?string $version = 'v1',
        ?AiSearchAnswer $recorded = null,
        ?EntityManagerInterface $entityManager = null,
        ?ManagerRegistry $registry = null,
    ): AiSiteSearch {
        $chunks = $this->createStub(AiSearchChunkRepository::class);
        $chunks->method('currentVersion')->willReturn($version);
        $chunks->method('search')->willReturn($excerpts);

        $answers = $this->createStub(AiSearchAnswerRepository::class);
        $answers->method('findOneByQuestionHash')->willReturn($recorded);

        return new AiSiteSearch(
            $client,
            $chunks,
            $answers,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(ConfigServiceInterface::class),
            $registry ?? $this->createStub(ManagerRegistry::class),
        );
    }
}
